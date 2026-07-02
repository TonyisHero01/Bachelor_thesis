import logging
import time
from datetime import datetime

from fastapi import FastAPI, HTTPException, Request, BackgroundTasks

from config import settings
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
from tfidf.search_service import (
    rebuild_search_index,
    partial_reindex_product,
    search_products,
    recommend_products,
    get_index_status,
)

from tfidf.search_index import search_index
from semantic_search.semantic_search_service import SemanticSearchService

from elastic_search.elastic_product_search_service import ElasticProductSearchService
from semantic_search.semantic_vector_repository import SemanticVectorRepository

semantic_search_service = SemanticSearchService()
elastic_service = ElasticProductSearchService()
semantic_repository = SemanticVectorRepository()

REINDEX_STATE = {
    "running": False,
    "last_started_at": None,
    "last_finished_at": None,
    "last_error": None,
    "last_updated": None,
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

def run_full_reindex_background(reason=None, context=None):
    if REINDEX_STATE["running"]:
        return

    REINDEX_STATE["running"] = True
    REINDEX_STATE["last_started_at"] = datetime.utcnow().isoformat() + "Z"
    REINDEX_STATE["last_error"] = None

    try:
        updated = rebuild_search_index()
        REINDEX_STATE["last_updated"] = updated
        REINDEX_STATE["last_finished_at"] = datetime.utcnow().isoformat() + "Z"
        logger.info("[REINDEX][BACKGROUND] finished updated=%s", updated)
    except Exception as e:
        logger.exception("[REINDEX][BACKGROUND] failed")
        REINDEX_STATE["last_error"] = str(e)
    finally:
        REINDEX_STATE["running"] = False

def client_ip(request: Request) -> str:
    xff = request.headers.get("x-forwarded-for")
    if xff:
        return xff.split(",")[0].strip()

    return request.client.host if request.client else "unknown"


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
def semantic_reindex():
    return semantic_search_service.reindex()

@app.post("/semantic/search")
def semantic_search(request: SemanticSearchRequest, http_request: Request):
    started = time.perf_counter()

    query = request.query.strip()

    if query == "":
        logger.info("[SEARCH_API] method=semantic_vector endpoint=/semantic/search empty_query=true")
        raise HTTPException(status_code=400, detail="Query cannot be empty")

    logger.info(
        "[SEARCH_API] method=semantic_vector endpoint=/semantic/search started query=%r limit=%s ip=%s",
        query,
        request.limit,
        client_ip(http_request),
    )

    result = semantic_search_service.search(
        query=query,
        limit=request.limit,
    )

    elapsed_ms = (time.perf_counter() - started) * 1000
    result_count = len(result.get("results", [])) if isinstance(result, dict) else 0

    logger.info(
        "[SEARCH_API] method=semantic_vector endpoint=/semantic/search finished query=%r results=%s elapsed_ms=%.2f",
        query,
        result_count,
        elapsed_ms,
    )

    return result

@app.post("/semantic/similar")
def semantic_similar(request: SemanticSimilarRequest):
    return semantic_search_service.similar_products(
        product_id=request.product_id,
        limit=request.limit
    )

@app.post("/config/reload")
def reload_config_api(request: Request):
    verify_api_key(request)

    from repositories.product_repository import fetch_active_relevance_config

    try:
        config = fetch_active_relevance_config()
        search_index.config = config
        semantic_search_service.config = config
        elastic_service.config = config

        logger.info("[CONFIG] search config reloaded")

        return {
            "ok": True,
            "config": config,
            "ip": client_ip(request),
            "ts": datetime.utcnow().isoformat() + "Z",
        }

    except Exception:
        logger.exception("[CONFIG] reload failed")
        raise HTTPException(status_code=500, detail="Config reload failed")

@app.post("/search", response_model=SearchResponse)
def search_api(req: SearchRequest, request: Request):
    started = time.perf_counter()

    query = req.query.strip()

    if not query:
        logger.info("[SEARCH_API] method=tfidf endpoint=/search empty_query=true")
        return {"results": []}

    limit = min(req.limit, settings.max_search_limit)
    skip_log = request.headers.get("X-BENCHMARK") == "1"

    logger.info(
        "[SEARCH_API] method=tfidf endpoint=/search started query=%r limit=%s skip_log=%s ip=%s",
        query,
        limit,
        skip_log,
        client_ip(request),
    )

    results = search_products(
        query,
        limit,
        skip_log=skip_log,
    )

    elapsed_ms = (time.perf_counter() - started) * 1000

    logger.info(
        "[SEARCH_API] method=tfidf endpoint=/search finished query=%r results=%s elapsed_ms=%.2f",
        query,
        len(results),
        elapsed_ms,
    )

    return {"results": results}

@app.get("/config")
def get_config_api(request: Request):
    verify_api_key(request)

    from repositories.product_repository import fetch_active_relevance_config

    return fetch_active_relevance_config()

@app.post("/reindex", response_model=ReindexResponse)
def reindex(req: ReindexRequest, request: Request, background_tasks: BackgroundTasks):
    verify_api_key(request)

    started = datetime.utcnow()

    try:
        if req.mode == "check":
            status = get_index_status()

            return {
                "ok": True,
                "mode": "check",
                "product_rows": status["product_rows"],
                "distinct_skus": status["distinct_skus"],
                "vector_rows": status["vector_rows"],
                "reason": req.reason,
                "context": req.context,
                "ip": client_ip(request),
                "ts": started.isoformat() + "Z",
            }

        if req.mode == "partial":
            if not req.sku:
                raise HTTPException(status_code=400, detail="SKU is required for partial reindex")

            updated = partial_reindex_product(req.sku)

            return {
                "ok": True,
                "mode": "partial",
                "updated": updated,
                "reason": req.reason,
                "context": req.context,
                "ip": client_ip(request),
                "ts": started.isoformat() + "Z",
            }

        if REINDEX_STATE["running"]:
            return {
                "ok": True,
                "mode": "full",
                "updated": 0,
                "reason": "full reindex already running",
                "context": req.context,
                "ip": client_ip(request),
                "ts": started.isoformat() + "Z",
            }

        background_tasks.add_task(
            run_full_reindex_background,
            req.reason,
            req.context,
        )

        return {
            "ok": True,
            "mode": "full",
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
def reindex_status(request: Request):
    verify_api_key(request)
    return REINDEX_STATE

@app.post("/train")
def train_compat(
    req: ReindexRequest,
    request: Request,
    background_tasks: BackgroundTasks,
):
    return reindex(req, request, background_tasks)

def get_active_search_method() -> str:
    from repositories.product_repository import fetch_active_relevance_config

    try:
        config = fetch_active_relevance_config()
        method = str(config.get("search_method", "tfidf")).strip()

        if method == "":
            return "tfidf"

        return method

    except Exception:
        logger.exception("[RECOMMEND] failed to load active search method")
        return "tfidf"


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


def recommend_by_sku_with_active_method(sku: str, limit: int):
    sku = sku.strip()

    if sku == "":
        return []

    limit = min(limit, settings.max_search_limit)
    search_method = get_active_search_method()

    if search_method == "semantic_vector":
        product_id = semantic_repository.get_latest_product_id_by_sku(sku)

        if product_id is None:
            logger.warning(
                "[RECOMMEND] semantic_vector cannot resolve sku=%s",
                sku,
            )
            return []

        result = semantic_search_service.similar_products(
            product_id=product_id,
            limit=limit,
        )

        rows = result.get("results", []) if isinstance(result, dict) else []

        logger.info(
            "[RECOMMEND] method=semantic_vector sku=%s product_id=%s results=%s",
            sku,
            product_id,
            len(rows),
        )

        return normalize_recommendation_rows(rows)

    if search_method == "elasticsearch_bm25":
        logger.info(
            "[RECOMMEND] method=elasticsearch_bm25 fallback=tfidf sku=%s",
            sku,
        )

    rows = recommend_products(sku, limit)

    logger.info(
        "[RECOMMEND] method=tfidf sku=%s results=%s",
        sku,
        len(rows),
    )

    return normalize_recommendation_rows(rows)


def add_seed_recommendation_scores(scores, seed_sku: str, seed_weight: float, limit: int):
    rows = recommend_by_sku_with_active_method(seed_sku, limit)

    for row in rows:
        recommended_sku = str(row.get("product_sku", "")).strip()

        if recommended_sku == "":
            continue

        similarity = float(row.get("similarity", 0))

        if similarity <= 0:
            continue

        scores[recommended_sku] = scores.get(recommended_sku, 0.0) + similarity * seed_weight

@app.post("/recommend/session", response_model=RecommendResponse)
def recommend_session_api(req: SessionRecommendRequest):
    limit = min(req.limit, settings.max_search_limit)

    scores = {}
    seed_skus = {}

    current_sku = str(req.current_sku or "").strip()

    if current_sku:
        seed_skus[current_sku] = 1.0

    for sku in req.viewed_skus or []:
        sku = str(sku).strip()

        if sku:
            seed_skus[sku] = max(seed_skus.get(sku, 0.0), 0.70)

    for sku in req.cart_skus or []:
        sku = str(sku).strip()

        if sku:
            seed_skus[sku] = max(seed_skus.get(sku, 0.0), 0.90)

    for seed_sku, seed_weight in seed_skus.items():
        add_seed_recommendation_scores(
            scores=scores,
            seed_sku=seed_sku,
            seed_weight=seed_weight,
            limit=max(limit * 3, 10),
        )

    for seed_sku in seed_skus.keys():
        scores.pop(seed_sku, None)

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
        for sku, score in sorted_items[:limit]
    ]

    logger.info(
        "[RECOMMEND_SESSION] method=%s seeds=%s results=%s",
        get_active_search_method(),
        len(seed_skus),
        len(results),
    )

    return {"results": results}

@app.get("/recommend/{sku}", response_model=RecommendResponse)
def recommend_api(sku: str, limit: int = 10):
    sku = sku.strip()

    if not sku:
        return {"results": []}

    results = recommend_by_sku_with_active_method(
        sku=sku,
        limit=limit,
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
def elastic_reindex():

    products = semantic_repository.get_products_for_indexing()

    elastic_service.create_index()

    elastic_service.index_products(products)

    return {

        "status": "ok",

        "indexed_products": len(products),

    }

@app.post("/elastic/search")
def elastic_search(payload: dict, request: Request):
    started = time.perf_counter()

    query = str(payload.get("query", "")).strip()
    limit = int(payload.get("limit", 10))

    if query == "":
        logger.info("[SEARCH_API] method=elasticsearch_bm25 endpoint=/elastic/search empty_query=true")
        return {"results": []}

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

