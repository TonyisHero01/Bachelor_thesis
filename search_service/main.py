import json
import logging
import threading
import time

from datetime import datetime
from typing import Any

from fastapi import (
    BackgroundTasks,
    FastAPI,
    HTTPException,
    Request,
)

from config import settings
from repositories.product_repository import (
    fetch_active_search_method as fetch_active_search_method_from_db,
    fetch_active_relevance_config,
    fetch_relevance_config_by_method,
    fetch_all_relevance_configs,
)
from schemas import (
    SearchRequest,
    SearchResponse,
    ReindexRequest,
    ReindexResponse,
    RecommendResponse,
    SemanticSearchRequest,
    SemanticSimilarRequest,
    SessionRecommendRequest,
)
from lexical.search_service import (
    rebuild_search_index,
    partial_reindex_product,
    search_products,
    recommend_products,
    get_index_status,
)

from lexical.search_index import search_index
from semantic_search.semantic_search_service import SemanticSearchService

from elastic_search.elastic_product_search_service import ElasticProductSearchService
from semantic_search.semantic_vector_repository import SemanticVectorRepository

AUTO_REINDEX_LOCK = threading.Lock()
REINDEX_STATE_LOCK = threading.Lock()

SUPPORTED_SEARCH_METHODS = {
    "lexical",
    "semantic_vector",
    "elasticsearch_bm25",
}

ACTIVE_METHOD_CACHE = {
    "method": None,
    "expires_at": 0,
}

ACTIVE_METHOD_TTL_SECONDS = 30

READY_CACHE = {
    "lexical": {
        "ready": False,
        "checked_at": 0,
    },
    "semantic_vector": {
        "ready": False,
        "checked_at": 0,
    },
    "elasticsearch_bm25": {
        "ready": False,
        "checked_at": 0,
    },
}

READY_CACHE_TTL_SECONDS = 60

RECOMMEND_CACHE = {}
RECOMMEND_CACHE_TTL_SECONDS = 600

semantic_search_service: SemanticSearchService | None = None
elastic_service = ElasticProductSearchService()
semantic_repository = SemanticVectorRepository()

REINDEX_STATE = {
    "running": False,
    "last_started_at": None,
    "last_finished_at": None,
    "last_error": None,
    "last_updated": None,
    "last_method": None,
}

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(name)s: %(message)s",
    force=True,
)

logger = logging.getLogger("search_service")
logger.setLevel(logging.INFO)

app = FastAPI(
    title=settings.app_name,
    version=settings.app_version,
)

def normalize_search_method(method: str | None) -> str:
    method = str(method or "lexical").strip()

    if method not in SUPPORTED_SEARCH_METHODS:
        return "lexical"

    return method

DEFAULT_SESSION_RECOMMENDATION_SETTINGS = {
    "current_product_weight": 1.0,
    "viewed_product_weight": 0.70,
    "cart_product_weight": 0.90,

    "max_viewed_seeds": 5,
    "max_cart_seeds": 5,
    "max_total_seeds": 8,

    "candidate_multiplier": 2,
    "minimum_candidates": 10,
}


def parse_positive_int(
    value: Any,
    default: int,
) -> int:
    try:
        parsed = int(value)
    except (TypeError, ValueError):
        return default

    return parsed if parsed > 0 else default


def parse_non_negative_int(
    value: Any,
    default: int,
) -> int:
    try:
        parsed = int(value)
    except (TypeError, ValueError):
        return default

    return parsed if parsed >= 0 else default


def parse_non_negative_float(
    value: Any,
    default: float,
) -> float:
    try:
        parsed = float(value)
    except (TypeError, ValueError):
        return default

    return parsed if parsed >= 0 else default


def normalize_algorithm_settings(
    value: Any,
) -> dict:
    if isinstance(value, dict):
        return value

    if isinstance(value, str):
        try:
            parsed = json.loads(value)

            if isinstance(parsed, dict):
                return parsed
        except json.JSONDecodeError:
            logger.warning(
                "[CONFIG] invalid algorithm_settings JSON"
            )

    return {}


def get_runtime_method_config(
    search_method: str,
) -> dict:
    search_method = normalize_search_method(
        search_method
    )

    if search_method == "lexical":
        config = getattr(
            search_index,
            "config",
            {},
        )

        if (
            isinstance(config, dict)
            and config.get("search_method") == "lexical"
        ):
            return config

    if (
        search_method == "semantic_vector"
        and semantic_search_service is not None
    ):
        config = semantic_search_service.config

        if (
            isinstance(config, dict)
            and config.get("search_method")
            == "semantic_vector"
        ):
            return config

    if search_method == "elasticsearch_bm25":
        config = elastic_service.config

        if (
            isinstance(config, dict)
            and config.get("search_method")
            == "elasticsearch_bm25"
        ):
            return config

    return fetch_relevance_config_by_method(
        search_method
    )


def get_session_recommendation_settings(
    search_method: str,
) -> dict:
    config = get_runtime_method_config(
        search_method
    )

    algorithm_settings = (
        normalize_algorithm_settings(
            config.get(
                "algorithm_settings",
                {},
            )
        )
    )

    configured = algorithm_settings.get(
        "session_recommendation",
        {},
    )

    if not isinstance(configured, dict):
        configured = {}

    defaults = DEFAULT_SESSION_RECOMMENDATION_SETTINGS

    return {
        "current_product_weight":
            parse_non_negative_float(
                configured.get(
                    "current_product_weight"
                ),
                defaults[
                    "current_product_weight"
                ],
            ),

        "viewed_product_weight":
            parse_non_negative_float(
                configured.get(
                    "viewed_product_weight"
                ),
                defaults[
                    "viewed_product_weight"
                ],
            ),

        "cart_product_weight":
            parse_non_negative_float(
                configured.get(
                    "cart_product_weight"
                ),
                defaults[
                    "cart_product_weight"
                ],
            ),

        "max_viewed_seeds":
            parse_non_negative_int(
                configured.get(
                    "max_viewed_seeds"
                ),
                defaults[
                    "max_viewed_seeds"
                ],
            ),

        "max_cart_seeds":
            parse_non_negative_int(
                configured.get(
                    "max_cart_seeds"
                ),
                defaults[
                    "max_cart_seeds"
                ],
            ),

        "max_total_seeds":
            parse_positive_int(
                configured.get(
                    "max_total_seeds"
                ),
                defaults[
                    "max_total_seeds"
                ],
            ),

        "candidate_multiplier":
            parse_positive_int(
                configured.get(
                    "candidate_multiplier"
                ),
                defaults[
                    "candidate_multiplier"
                ],
            ),

        "minimum_candidates":
            parse_positive_int(
                configured.get(
                    "minimum_candidates"
                ),
                defaults[
                    "minimum_candidates"
                ],
            ),
    }

def clear_active_method_cache():
    ACTIVE_METHOD_CACHE["method"] = None
    ACTIVE_METHOD_CACHE["expires_at"] = 0


def clear_ready_cache(search_method: str | None = None):
    if search_method is None:
        for item in READY_CACHE.values():
            item["ready"] = False
            item["checked_at"] = 0
        return

    search_method = normalize_search_method(search_method)

    READY_CACHE[search_method]["ready"] = False
    READY_CACHE[search_method]["checked_at"] = 0


def clear_runtime_caches(search_method: str | None = None):
    clear_recommend_cache()
    clear_ready_cache(search_method)

def get_recommend_cache(cache_key: str):
    item = RECOMMEND_CACHE.get(cache_key)

    if item is None:
        return None

    if time.time() - item["created_at"] > RECOMMEND_CACHE_TTL_SECONDS:
        RECOMMEND_CACHE.pop(cache_key, None)
        return None

    return item["results"]


def set_recommend_cache(cache_key: str, results):
    RECOMMEND_CACHE[cache_key] = {
        "created_at": time.time(),
        "results": results,
    }


def clear_recommend_cache():
    RECOMMEND_CACHE.clear()

def get_semantic_search_service() -> SemanticSearchService:
    global semantic_search_service

    if semantic_search_service is None:
        logger.info("[SEMANTIC] loading semantic search service")
        semantic_search_service = SemanticSearchService()
        logger.info("[SEMANTIC] semantic search service loaded")

    return semantic_search_service

def run_full_reindex_background(
    reason=None,
    context=None,
    search_method=None,
):
    with REINDEX_STATE_LOCK:
        if REINDEX_STATE["running"]:
            logger.info(
                "[REINDEX][BACKGROUND] skipped because "
                "another reindex is already running"
            )
            return

        if search_method is None:
            search_method = get_active_search_method()

        search_method = normalize_search_method(
            search_method
        )

        REINDEX_STATE["running"] = True
        REINDEX_STATE["last_started_at"] = (
            datetime.utcnow().isoformat() + "Z"
        )
        REINDEX_STATE["last_finished_at"] = None
        REINDEX_STATE["last_error"] = None
        REINDEX_STATE["last_updated"] = None
        REINDEX_STATE["last_method"] = search_method

    try:
        result = reindex_active_method_full(
            search_method
        )

        clear_runtime_caches(
            search_method
        )

        with REINDEX_STATE_LOCK:
            REINDEX_STATE["last_updated"] = result
            REINDEX_STATE["last_finished_at"] = (
                datetime.utcnow().isoformat() + "Z"
            )

        logger.info(
            "[REINDEX][BACKGROUND] "
            "finished method=%s result=%s",
            search_method,
            result,
        )

    except Exception as exc:
        logger.exception(
            "[REINDEX][BACKGROUND] "
            "failed method=%s",
            search_method,
        )

        with REINDEX_STATE_LOCK:
            REINDEX_STATE["last_error"] = str(exc)
            REINDEX_STATE["last_finished_at"] = (
                datetime.utcnow().isoformat() + "Z"
            )

    finally:
        with REINDEX_STATE_LOCK:
            REINDEX_STATE["running"] = False

def reindex_active_method_full(
    search_method: str,
):
    search_method = normalize_search_method(
        search_method
    )

    if search_method == "semantic_vector":
        logger.info(
            "[REINDEX] method=semantic_vector "
            "full reindex started"
        )

        service = get_semantic_search_service()
        service.reload_config()

        result = service.reindex()

        logger.info(
            "[REINDEX] method=semantic_vector "
            "full reindex finished result=%s",
            result,
        )

        return {
            "method": "semantic_vector",
            "result": result,
        }

    if search_method == "elasticsearch_bm25":
        logger.info(
            "[REINDEX] method=elasticsearch_bm25 "
            "full reindex started"
        )

        elastic_service.reload_config()

        products = (
            semantic_repository
            .get_products_for_indexing()
        )

        elastic_service.create_index()

        indexed_count = (
            elastic_service.index_products(
                products
            )
        )

        result = {
            "method": "elasticsearch_bm25",
            "indexed_products": indexed_count,
        }

        logger.info(
            "[REINDEX] method=elasticsearch_bm25 "
            "full reindex finished result=%s",
            result,
        )

        return result

    logger.info(
        "[REINDEX] method=lexical "
        "full reindex started"
    )

    updated = rebuild_search_index()

    result = {
        "method": "lexical",
        "updated": updated,
    }

    logger.info(
        "[REINDEX] method=lexical "
        "full reindex finished result=%s",
        result,
    )

    return result

def reindex_active_method_partial(
    search_method: str,
    sku: str,
):
    search_method = normalize_search_method(
        search_method
    )

    sku = str(
        sku or ""
    ).strip()

    if not sku:
        raise HTTPException(
            status_code=400,
            detail=(
                "SKU is required for partial reindex"
            ),
        )

    if search_method == "semantic_vector":
        logger.info(
            "[REINDEX] method=semantic_vector "
            "partial reindex sku=%s",
            sku,
        )

        service = get_semantic_search_service()
        service.reload_config()

        result = service.reindex_product_by_sku(
            sku
        )

        return {
            "method": "semantic_vector",
            "mode": "partial",
            "sku": sku,
            "result": result,
        }

    if search_method == "elasticsearch_bm25":
        logger.info(
            "[REINDEX] method=elasticsearch_bm25 "
            "partial reindex sku=%s",
            sku,
        )

        elastic_service.reload_config()

        result = (
            elastic_service
            .reindex_product_by_sku(
                sku
            )
        )

        return {
            "method": "elasticsearch_bm25",
            "mode": "partial",
            "sku": sku,
            "result": result,
        }

    logger.info(
        "[REINDEX] method=lexical "
        "partial reindex sku=%s",
        sku,
    )

    updated = partial_reindex_product(
        sku
    )

    return {
        "method": "lexical",
        "mode": "partial",
        "sku": sku,
        "updated": updated,
    }

def client_ip(request: Request) -> str:
    forwarded_for = request.headers.get(
        "x-forwarded-for"
    )

    if forwarded_for:
        return forwarded_for.split(",")[0].strip()

    if request.client:
        return request.client.host

    return "unknown"

def verify_api_key(request: Request) -> None:
    if not settings.api_key:
        return

    token = request.headers.get("X-API-KEY")

    if token != settings.api_key:
        raise HTTPException(status_code=403, detail="Forbidden")


@app.get("/health")
def health():
    return {
        "ok": True,
        "service": settings.app_name,
        "version": settings.app_version,
    }

@app.post("/semantic/reindex")
def semantic_reindex(
    request: Request,
):
    verify_api_key(request)
    started = time.perf_counter()

    logger.info(
        "[SEMANTIC_REINDEX] request received ip=%s",
        client_ip(request),
    )

    try:
        logger.info("[SEMANTIC_REINDEX] loading semantic service if needed")

        service = get_semantic_search_service()
        service.reload_config()

        logger.info(
            "[SEMANTIC_REINDEX] semantic service ready, starting reindex"
        )

        result = service.reindex()
        clear_runtime_caches("semantic_vector")

        elapsed_ms = (time.perf_counter() - started) * 1000

        logger.info(
            "[SEMANTIC_REINDEX] finished elapsed_ms=%.2f result=%s",
            elapsed_ms,
            result,
        )

        return result

    except Exception as exc:
        elapsed_ms = (time.perf_counter() - started) * 1000

        logger.exception(
            "[SEMANTIC_REINDEX] failed elapsed_ms=%.2f error=%s",
            elapsed_ms,
            exc,
        )

        raise HTTPException(
            status_code=500,
            detail="Semantic reindex failed",
        )

@app.post("/lexical/search")
def lexical_search(payload: dict, request: Request):
    started = time.perf_counter()

    query = str(payload.get("query", "")).strip()
    limit = min(
        parse_positive_int(
            payload.get("limit"),
            10,
        ),
        settings.max_search_limit,
    )

    if query == "":
        logger.info("[SEARCH_API] method=lexical endpoint=/lexical/search empty_query=true")
        return {"results": []}

    ensure_method_ready("lexical")

    logger.info(
        "[SEARCH_API] method=lexical endpoint=/lexical/search started query=%r limit=%s ip=%s",
        query,
        limit,
        client_ip(request),
    )

    results = search_products(
        query,
        limit,
    )

    results = normalize_search_rows(results)

    elapsed_ms = (time.perf_counter() - started) * 1000

    logger.info(
        "[SEARCH_API] method=lexical endpoint=/lexical/search finished query=%r results=%s elapsed_ms=%.2f",
        query,
        len(results),
        elapsed_ms,
    )

    return {
        "method": "lexical",
        "query": query,
        "limit": limit,
        "results": results,
    }

@app.post("/semantic/search")
def semantic_search(
    request: SemanticSearchRequest,
    http_request: Request,
):
    started = time.perf_counter()

    query = str(
        request.query or ""
    ).strip()

    limit = min(
        parse_positive_int(
            request.limit,
            10,
        ),
        settings.max_search_limit,
    )

    if not query:
        logger.info(
            "[SEARCH_API] method=semantic_vector "
            "endpoint=/semantic/search empty_query=true"
        )

        raise HTTPException(
            status_code=400,
            detail="Query cannot be empty",
        )

    ensure_method_ready(
        "semantic_vector"
    )

    logger.info(
        "[SEARCH_API] method=semantic_vector "
        "endpoint=/semantic/search started "
        "query=%r limit=%s ip=%s",
        query,
        limit,
        client_ip(http_request),
    )

    result = (
        get_semantic_search_service()
        .search(
            query=query,
            limit=limit,
        )
    )

    elapsed_ms = (
        time.perf_counter() - started
    ) * 1000

    result_count = (
        len(result.get("results", []))
        if isinstance(result, dict)
        else 0
    )

    logger.info(
        "[SEARCH_API] method=semantic_vector "
        "endpoint=/semantic/search finished "
        "query=%r results=%s elapsed_ms=%.2f",
        query,
        result_count,
        elapsed_ms,
    )

    return result

@app.post("/semantic/similar")
def semantic_similar(
    request: SemanticSimilarRequest,
):
    limit = min(
        parse_positive_int(
            request.limit,
            10,
        ),
        settings.max_search_limit,
    )

    ensure_method_ready(
        "semantic_vector"
    )

    return (
        get_semantic_search_service()
        .similar_products(
            product_id=request.product_id,
            limit=limit,
        )
    )

@app.post("/config/reload")
def reload_config_api(
    request: Request,
):
    verify_api_key(request)

    try:
        configs = fetch_all_relevance_configs()

        lexical_config = configs["lexical"]
        semantic_config = configs["semantic_vector"]
        elastic_config = configs["elasticsearch_bm25"]

        search_index.config = lexical_config

        if semantic_search_service is not None:
            semantic_search_service.reload_config(
                semantic_config
            )

        elastic_service.reload_config(
            elastic_config
        )

        clear_active_method_cache()
        clear_ready_cache()
        clear_recommend_cache()

        active_method = (
            fetch_active_search_method_from_db()
        )

        logger.info(
            "[CONFIG] all method configurations "
            "reloaded active_method=%s",
            active_method,
        )

        return {
            "ok": True,
            "active_method": active_method,
            "configs": configs,
            "ip": client_ip(request),
            "ts": (
                datetime.utcnow().isoformat()
                + "Z"
            ),
        }

    except Exception:
        logger.exception(
            "[CONFIG] reload failed"
        )

        raise HTTPException(
            status_code=500,
            detail="Config reload failed",
        )
    
def save_search_query_log(
    query: str,
    method: str,
    result_count: int,
    response_time_ms: float,
):
    import psycopg2

    try:
        conn = psycopg2.connect(settings.database_url)
        cur = conn.cursor()

        cur.execute(
            """
            INSERT INTO search_query_log (
                query,
                method,
                result_count,
                response_time_ms,
                created_at
            )
            VALUES (%s, %s, %s, %s, NOW())
            """,
            (
                query,
                method,
                result_count,
                response_time_ms,
            ),
        )

        conn.commit()

        cur.close()
        conn.close()

    except Exception:
        logger.exception(
            "[SEARCH_LOG] failed to save search query log query=%r method=%s",
            query,
            method,
        )

@app.post("/search", response_model=SearchResponse)
def search_api(
    req: SearchRequest,
    request: Request,
    background_tasks: BackgroundTasks,
):
    started = time.perf_counter()

    query = req.query.strip()
    search_method = get_active_search_method()

    if not query:
        logger.info(
            "[SEARCH_API] method=%s endpoint=/search empty_query=true",
            search_method,
        )
        return {"results": []}

    limit = min(
        parse_positive_int(
            req.limit,
            10,
        ),
        settings.max_search_limit,
    )
    skip_log = request.headers.get("X-BENCHMARK") == "1"

    logger.info(
        "[SEARCH_API] method=%s endpoint=/search started query=%r limit=%s skip_log=%s ip=%s",
        search_method,
        query,
        limit,
        skip_log,
        client_ip(request),
    )

    results = search_with_method(
        search_method=search_method,
        query=query,
        limit=limit,
    )

    elapsed_ms = (time.perf_counter() - started) * 1000

    if not skip_log:
        background_tasks.add_task(
            save_search_query_log,
            query,
            search_method,
            len(results),
            elapsed_ms,
        )

    logger.info(
        "[SEARCH_API] method=%s endpoint=/search finished query=%r results=%s elapsed_ms=%.2f",
        search_method,
        query,
        len(results),
        elapsed_ms,
    )

    return {"results": results}

@app.get("/config")
def get_config_api(
    request: Request,
):
    verify_api_key(request)

    return fetch_active_relevance_config()

@app.get("/config/all")
def get_all_configs_api(
    request: Request,
):
    verify_api_key(request)

    return {
        "active_method":
            fetch_active_search_method_from_db(),
        "configs":
            fetch_all_relevance_configs(),
    }


@app.get("/config/method/{search_method}")
def get_method_config_api(
    search_method: str,
    request: Request,
):
    verify_api_key(request)

    if (
        search_method
        not in SUPPORTED_SEARCH_METHODS
    ):
        raise HTTPException(
            status_code=400,
            detail=(
                "Unsupported search method: "
                f"{search_method}"
            ),
        )

    return fetch_relevance_config_by_method(
        search_method
    )

@app.post("/reindex", response_model=ReindexResponse)
def reindex(req: ReindexRequest, request: Request, background_tasks: BackgroundTasks):
    verify_api_key(request)

    started = datetime.utcnow()

    try:
        if req.mode == "check":
            search_method = get_active_search_method()
            status = get_index_status()

            semantic_vectors = semantic_repository.count_semantic_vectors()

            elastic_count = 0

            try:
                if elastic_service.client.indices.exists(index="products"):
                    response = elastic_service.client.count(index="products")
                    elastic_count = int(response.get("count", 0))
            except Exception:
                logger.warning("[REINDEX] failed to read elastic index count")

            return {
                "ok": True,
                "mode": "check",
                "active_method": search_method,
                "product_rows": status["product_rows"],
                "distinct_skus": status["distinct_skus"],
                "lexical_vector_rows": status["vector_rows"],
                "semantic_vector_rows": semantic_vectors,
                "elastic_index_rows": elastic_count,
                "reason": req.reason,
                "context": req.context,
                "ip": client_ip(request),
                "ts": started.isoformat() + "Z",
            }
        if req.mode == "partial":
            if not req.sku:
                raise HTTPException(
                    status_code=400,
                    detail="SKU is required for partial reindex",
                )

            search_method = get_active_search_method()

            result = reindex_active_method_partial(
                search_method=search_method,
                sku=req.sku,
            )
            clear_runtime_caches(search_method)

            return {
                "ok": True,
                "mode": "partial",
                "active_method": search_method,
                "result": result,
                "reason": req.reason,
                "context": req.context,
                "ip": client_ip(request),
                "ts": started.isoformat() + "Z",
            }

        with REINDEX_STATE_LOCK:
            reindex_running = bool(
                REINDEX_STATE["running"]
            )

        if reindex_running:
            return {
                "ok": True,
                "mode": "full",
                "updated": 0,
                "reason": "full reindex already running",
                "context": req.context,
                "ip": client_ip(request),
                "ts": started.isoformat() + "Z",
            }

        search_method = get_active_search_method()

        background_tasks.add_task(
            run_full_reindex_background,
            req.reason,
            req.context,
            search_method,
        )

        return {
            "ok": True,
            "mode": "full",
            "active_method": search_method,
            "updated": 0,
            "reason": "full reindex queued",
            "context": req.context,
            "ip": client_ip(request),
            "ts": started.isoformat() + "Z",
        }
    except HTTPException:
        raise
    except Exception:
        logger.exception("Reindex failed")
        raise HTTPException(status_code=500, detail="Reindex failed")
    
@app.get("/reindex/status")
def reindex_status(
    request: Request,
):
    verify_api_key(request)
    with REINDEX_STATE_LOCK:
        return dict(REINDEX_STATE)

@app.post("/train")
def train_compat(
    req: ReindexRequest,
    request: Request,
    background_tasks: BackgroundTasks,
):
    return reindex(req, request, background_tasks)

def normalize_search_rows(rows):
    normalized = []

    for row in rows:
        if not isinstance(row, dict):
            continue

        item = dict(row)

        if "product_sku" not in item and "sku" in item:
            item["product_sku"] = item["sku"]

        if "sku" not in item and "product_sku" in item:
            item["sku"] = item["product_sku"]

        if "product_sku" not in item:
            continue

        if "similarity" not in item:
            item["similarity"] = 0.0

        normalized.append(item)

    return normalized

def search_with_method(
    search_method: str,
    query: str,
    limit: int,
):
    search_method = normalize_search_method(
        search_method
    )

    query = str(
        query or ""
    ).strip()

    limit = min(
        parse_positive_int(
            limit,
            10,
        ),
        settings.max_search_limit,
    )

    if not query:
        return []

    ensure_method_ready(
        search_method
    )

    if search_method == "semantic_vector":
        result = (
            get_semantic_search_service()
            .search(
                query=query,
                limit=limit,
            )
        )

        rows = (
            result.get("results", [])
            if isinstance(result, dict)
            else []
        )

        return normalize_search_rows(
            rows
        )

    if search_method == "elasticsearch_bm25":
        result = elastic_service.search(
            query=query,
            limit=limit,
        )

        rows = (
            result.get("results", [])
            if isinstance(result, dict)
            else []
        )

        return normalize_search_rows(
            rows
        )

    rows = search_products(
        query,
        limit,
    )

    return normalize_search_rows(
        rows
    )

def get_active_search_method() -> str:
    now = time.time()

    cached_method = ACTIVE_METHOD_CACHE.get(
        "method"
    )

    cached_until = ACTIVE_METHOD_CACHE.get(
        "expires_at",
        0,
    )

    if (
        cached_method is not None
        and now < cached_until
    ):
        return normalize_search_method(
            cached_method
        )

    try:
        method = normalize_search_method(
            fetch_active_search_method_from_db()
        )

        ACTIVE_METHOD_CACHE["method"] = method
        ACTIVE_METHOD_CACHE["expires_at"] = (
            now + ACTIVE_METHOD_TTL_SECONDS
        )

        return method

    except Exception:
        logger.exception(
            "[CONFIG] failed to load "
            "active search method"
        )

        return "lexical"

def normalize_recommendation_rows(rows):
    normalized = []

    for row in rows:
        if not isinstance(row, dict):
            continue

        item = dict(row)

        if "product_sku" not in item and "sku" in item:
            item["product_sku"] = item["sku"]

        if "sku" not in item and "product_sku" in item:
            item["sku"] = item["product_sku"]

        if "product_sku" not in item:
            continue

        if "similarity" not in item:
            continue

        normalized.append(item)

    return normalized


def recommend_by_sku_with_active_method(
    sku: str,
    limit: int,
    search_method: str | None = None,
):
    sku = str(
        sku or ""
    ).strip()

    if not sku:
        return []

    limit = min(
        parse_positive_int(
            limit,
            10,
        ),
        settings.max_search_limit,
    )

    if search_method is None:
        search_method = get_active_search_method()

    search_method = normalize_search_method(search_method)

    cache_key = f"{search_method}:{sku}:{limit}"
    cached_results = get_recommend_cache(cache_key)

    if cached_results is not None:
        logger.info(
            "[RECOMMEND_CACHE] hit method=%s sku=%s limit=%s results=%s",
            search_method,
            sku,
            limit,
            len(cached_results),
        )
        return cached_results

    ensure_method_ready(search_method, sku=sku)

    if search_method == "semantic_vector":
        product_id = semantic_repository.get_latest_product_id_by_sku(sku)

        if product_id is None:
            logger.warning(
                "[RECOMMEND] semantic_vector cannot resolve sku=%s",
                sku,
            )
            return []

        result = get_semantic_search_service().similar_products(
            product_id=product_id,
            limit=limit,
        )

        rows = result.get("results", []) if isinstance(result, dict) else []
        results = normalize_recommendation_rows(rows)

        logger.info(
            "[RECOMMEND] method=semantic_vector sku=%s product_id=%s results=%s",
            sku,
            product_id,
            len(results),
        )

        set_recommend_cache(cache_key, results)

        return results

    if search_method == "elasticsearch_bm25":
        result = elastic_service.recommend_by_sku(
            sku=sku,
            limit=limit,
        )

        rows = result.get("results", []) if isinstance(result, dict) else []
        results = normalize_recommendation_rows(rows)

        logger.info(
            "[RECOMMEND] method=elasticsearch_bm25 sku=%s results=%s",
            sku,
            len(results),
        )

        set_recommend_cache(cache_key, results)

        return results

    rows = recommend_products(sku, limit)
    results = normalize_recommendation_rows(rows)

    logger.info(
        "[RECOMMEND] method=lexical sku=%s results=%s",
        sku,
        len(results),
    )

    set_recommend_cache(cache_key, results)

    return results

def add_seed_recommendation_scores(
    scores,
    seed_sku: str,
    seed_weight: float,
    limit: int,
    search_method: str | None = None,
):
    rows = recommend_by_sku_with_active_method(
        sku=seed_sku,
        limit=limit,
        search_method=search_method,
    )

    for row in rows:
        recommended_sku = str(row.get("product_sku", "")).strip()

        if recommended_sku == "":
            continue

        similarity = float(row.get("similarity", 0))

        if similarity <= 0:
            continue

        scores[recommended_sku] = scores.get(recommended_sku, 0.0) + similarity * seed_weight

def build_recommendations_from_weighted_seeds(
    weighted_seeds,
    limit: int,
    search_method: str | None = None,
):
    limit = min(
        parse_positive_int(
            limit,
            10,
        ),
        settings.max_search_limit,
    )

    if search_method is None:
        search_method = (
            get_active_search_method()
        )

    search_method = normalize_search_method(
        search_method
    )

    session_settings = (
        get_session_recommendation_settings(
            search_method
        )
    )

    max_total_seeds = session_settings[
        "max_total_seeds"
    ]

    candidate_multiplier = (
        session_settings[
            "candidate_multiplier"
        ]
    )

    minimum_candidates = (
        session_settings[
            "minimum_candidates"
        ]
    )

    scores: dict[str, float] = {}
    seed_skus: dict[str, float] = {}

    for item in weighted_seeds:
        if not isinstance(item, dict):
            continue

        sku = str(
            item.get("sku", "")
        ).strip()

        if not sku:
            continue

        try:
            weight = float(
                item.get(
                    "weight",
                    1.0,
                )
            )
        except (TypeError, ValueError):
            weight = 1.0

        if weight <= 0:
            continue

        seed_skus[sku] = max(
            seed_skus.get(
                sku,
                0.0,
            ),
            weight,
        )

    seed_items = sorted(
        seed_skus.items(),
        key=lambda item: item[1],
        reverse=True,
    )[:max_total_seeds]

    candidate_limit = max(
        limit * candidate_multiplier,
        minimum_candidates,
    )

    for seed_sku, seed_weight in seed_items:
        add_seed_recommendation_scores(
            scores=scores,
            seed_sku=seed_sku,
            seed_weight=seed_weight,
            limit=candidate_limit,
            search_method=search_method,
        )

    for seed_sku in seed_skus:
        scores.pop(
            seed_sku,
            None,
        )

    sorted_items = sorted(
        scores.items(),
        key=lambda item: item[1],
        reverse=True,
    )

    results = [
        {
            "product_sku": sku,
            "sku": sku,
            "similarity": score,
        }
        for sku, score
        in sorted_items[:limit]
    ]

    logger.info(
        "[RECOMMEND_BATCH_BUILD] "
        "method=%s seeds=%s used_seeds=%s "
        "candidate_limit=%s results=%s",
        search_method,
        len(seed_skus),
        len(seed_items),
        candidate_limit,
        len(results),
    )

    return results

@app.post("/recommend/batch", response_model=RecommendResponse)
def recommend_batch_api(payload: dict):
    limit = min(
        parse_positive_int(
            payload.get("limit"),
            10,
        ),
        settings.max_search_limit,
    )

    seeds = payload.get("seeds", [])

    if not isinstance(seeds, list):
        raise HTTPException(
            status_code=400,
            detail="seeds must be a list",
        )

    search_method = get_active_search_method()

    results = build_recommendations_from_weighted_seeds(
        weighted_seeds=seeds,
        limit=limit,
        search_method=search_method,
    )

    return {
        "results": results,
    }

@app.post(
    "/recommend/session",
    response_model=RecommendResponse,
)
def recommend_session_api(
    req: SessionRecommendRequest,
):
    limit = min(
        parse_positive_int(
            req.limit,
            10,
        ),
        settings.max_search_limit,
    )

    search_method = (
        get_active_search_method()
    )

    session_settings = (
        get_session_recommendation_settings(
            search_method
        )
    )

    current_weight = session_settings[
        "current_product_weight"
    ]

    viewed_weight = session_settings[
        "viewed_product_weight"
    ]

    cart_weight = session_settings[
        "cart_product_weight"
    ]

    max_viewed_seeds = session_settings[
        "max_viewed_seeds"
    ]

    max_cart_seeds = session_settings[
        "max_cart_seeds"
    ]

    seeds = []

    current_sku = str(
        req.current_sku or ""
    ).strip()

    if current_sku and current_weight > 0:
        seeds.append({
            "sku": current_sku,
            "weight": current_weight,
        })

    viewed_skus = [
        str(sku).strip()
        for sku in (req.viewed_skus or [])
        if str(sku).strip()
    ]

    cart_skus = [
        str(sku).strip()
        for sku in (req.cart_skus or [])
        if str(sku).strip()
    ]

    selected_viewed_skus = (
        viewed_skus[-max_viewed_seeds:]
        if max_viewed_seeds > 0
        else []
    )

    selected_cart_skus = (
        cart_skus[-max_cart_seeds:]
        if max_cart_seeds > 0
        else []
    )

    if viewed_weight > 0:
        for sku in selected_viewed_skus:
            seeds.append({
                "sku": sku,
                "weight": viewed_weight,
            })

    if cart_weight > 0:
        for sku in selected_cart_skus:
            seeds.append({
                "sku": sku,
                "weight": cart_weight,
            })

    results = (
        build_recommendations_from_weighted_seeds(
            weighted_seeds=seeds,
            limit=limit,
            search_method=search_method,
        )
    )

    logger.info(
        "[RECOMMEND_SESSION] "
        "method=%s input_seeds=%s results=%s",
        search_method,
        len(seeds),
        len(results),
    )

    return {
        "results": results,
    }

@app.get("/recommend/{sku}", response_model=RecommendResponse)
def recommend_api(sku: str, limit: int = 10):
    sku = sku.strip()

    if not sku:
        return {"results": []}

    search_method = get_active_search_method()

    results = recommend_by_sku_with_active_method(
        sku=sku,
        limit=limit,
        search_method=search_method,
    )

    return {"results": results}

@app.get("/search-log/stats")
def search_log_stats():
    import psycopg2

    conn = psycopg2.connect(settings.database_url)
    cur = conn.cursor()

    cur.execute("""
        SELECT method,
               COUNT(*),
               AVG(response_time_ms),
               AVG(result_count)
        FROM search_query_log
        GROUP BY method
    """)

    rows = cur.fetchall()

    cur.close()
    conn.close()

    return {
        "stats": [
            {
                "method": r[0],
                "count": r[1],
                "avg_time_ms": float(r[2]),
                "avg_results": float(r[3]),
            }
            for r in rows
        ]
    }

@app.post("/elastic/reindex")
def elastic_reindex(
    request: Request,
):
    verify_api_key(request)

    elastic_service.reload_config()

    products = (
        semantic_repository
        .get_products_for_indexing()
    )

    elastic_service.create_index()

    indexed_count = (
        elastic_service.index_products(
            products
        )
    )

    clear_runtime_caches(
        "elasticsearch_bm25"
    )

    return {
        "status": "ok",
        "indexed_products": indexed_count,
    }

@app.post("/elastic/search")
def elastic_search(payload: dict, request: Request):
    started = time.perf_counter()

    query = str(payload.get("query", "")).strip()
    limit = min(
        parse_positive_int(
            payload.get("limit"),
            10,
        ),
        settings.max_search_limit,
    )

    if query == "":
        logger.info("[SEARCH_API] method=elasticsearch_bm25 endpoint=/elastic/search empty_query=true")
        return {"results": []}

    ensure_method_ready("elasticsearch_bm25")

    logger.info(
        "[SEARCH_API] method=elasticsearch_bm25 endpoint=/elastic/search started query=%r limit=%s ip=%s",
        query,
        limit,
        client_ip(request),
    )

    result = elastic_service.search(query, limit)

    elapsed_ms = (time.perf_counter() - started) * 1000
    result_count = len(result.get("results", [])) if isinstance(result, dict) else 0

    logger.info(
        "[SEARCH_API] method=elasticsearch_bm25 endpoint=/elastic/search finished query=%r results=%s elapsed_ms=%.2f",
        query,
        result_count,
        elapsed_ms,
    )

    return result

@app.get("/ready")
def ready():
    return {
        "ready": True,
        "service": settings.app_name,
        "version": settings.app_version,
    }

def ensure_method_ready(search_method: str, sku: str | None = None):
    search_method = normalize_search_method(search_method)

    with AUTO_REINDEX_LOCK:
        if search_method == "lexical":
            ensure_lexical_ready_cached()
            return

        if search_method == "semantic_vector":
            ensure_semantic_ready_cached(sku)
            return

        if search_method == "elasticsearch_bm25":
            ensure_elastic_ready_cached()
            return


def ensure_active_method_ready(search_method: str, sku: str | None = None):
    ensure_method_ready(search_method, sku)

def ensure_lexical_ready_cached():
    now = time.time()
    cache = READY_CACHE["lexical"]

    if cache["ready"] and now - cache["checked_at"] < READY_CACHE_TTL_SECONDS:
        return

    status = get_index_status()

    vector_rows = int(status.get("vector_rows", 0))
    indexed_documents = int(status.get("indexed_documents", 0))

    if vector_rows > 0 and indexed_documents > 0:
        cache["ready"] = True
        cache["checked_at"] = now
        return

    logger.warning(
        "[AUTO_REINDEX] Lexical index not ready vector_rows=%s indexed_documents=%s",
        vector_rows,
        indexed_documents,
    )

    rebuild_search_index()

    cache["ready"] = True
    cache["checked_at"] = time.time()

    logger.info("[AUTO_REINDEX] Lexical reindex finished")


def ensure_semantic_ready_cached(sku: str | None = None):
    now = time.time()
    cache = READY_CACHE["semantic_vector"]

    if sku:
        sku = str(sku).strip()

        if sku == "":
            return

        if semantic_repository.has_product_vector_by_sku(sku):
            return

        logger.warning(
            "[AUTO_REINDEX] Semantic vector missing for sku=%s",
            sku,
        )

        service = get_semantic_search_service()
        service.reload_config()

        service.reindex_product_by_sku(
            sku
        )

        clear_recommend_cache()

        return

    if cache["ready"] and now - cache["checked_at"] < READY_CACHE_TTL_SECONDS:
        return

    count = semantic_repository.count_semantic_vectors()

    if count > 0:
        cache["ready"] = True
        cache["checked_at"] = now
        return

    logger.warning(
        "[AUTO_REINDEX] Semantic index not ready vectors=%s",
        count,
    )

    service = get_semantic_search_service()
    service.reload_config()
    service.reindex()

    cache["ready"] = True
    cache["checked_at"] = time.time()

    clear_recommend_cache()

    logger.info("[AUTO_REINDEX] Semantic reindex finished")


def ensure_elastic_ready_cached():
    now = time.time()
    cache = READY_CACHE["elasticsearch_bm25"]

    if cache["ready"] and now - cache["checked_at"] < READY_CACHE_TTL_SECONDS:
        return

    try:
        if elastic_service.client.indices.exists(index="products"):
            response = elastic_service.client.count(index="products")

            if int(response.get("count", 0)) > 0:
                cache["ready"] = True
                cache["checked_at"] = now
                return

        logger.warning(
            "[AUTO_REINDEX] Elasticsearch "
            "index not ready"
        )

        elastic_service.reload_config()

        products = (
            semantic_repository
            .get_products_for_indexing()
        )

        elastic_service.create_index()
        indexed_count = (
            elastic_service.index_products(
                products
            )
        )

        cache["ready"] = True
        cache["checked_at"] = time.time()

        clear_recommend_cache()

        logger.info(
            "[AUTO_REINDEX] Elasticsearch reindex finished indexed_products=%s",
            indexed_count,
        )

    except Exception:
        logger.exception("[AUTO_REINDEX] Elasticsearch reindex failed")
        raise