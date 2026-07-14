from fastapi import FastAPI, Request
from fastapi.responses import (
    HTMLResponse,
    PlainTextResponse,
    RedirectResponse,
)
from http_client import request_json
from benchmark_service import run_benchmark, rows_to_csv
from evaluation_service import run_evaluation
from recommendation_log_service import (
    fetch_recommendation_event_log_report,
    fetch_recommendation_event_log_csv,
)
from html_pages import render_benchmark_page, render_evaluation_page
from report_storage import save_evaluation_report, load_latest_evaluation_report
from recommendation_metrics_service import fetch_recommendation_event_metrics
import requests
from config import settings

from fastapi.staticfiles import StaticFiles
from user_studies.app import router as user_studies_router
from user_study_metrics_service import fetch_user_study_metrics

app = FastAPI(title="E-shop Search Benchmark")

app.include_router(user_studies_router)

app.mount(
    "/user-studies/static",
    StaticFiles(directory="user_studies/static"),
    name="user_studies_static",
)

LAST_RESULTS = []

SEARCH_METHODS = {
    "lexical",
    "semantic_vector",
    "elasticsearch_bm25",
}

def build_recommendation_log_filters(
    eventType: str = "",
    pageType: str = "",
    algorithm: str = "",
    sessionId: str = "",
    customerId: str = "",
    sourceSku: str = "",
    recommendedSku: str = "",
    dateFrom: str = "",
    dateTo: str = "",
    limit: int = 200,
):
    return {
        "event_type": eventType,
        "page_type": pageType,
        "algorithm": algorithm,
        "session_id": sessionId,
        "customer_id": customerId,
        "source_sku": sourceSku,
        "recommended_sku": recommendedSku,
        "date_from": dateFrom,
        "date_to": dateTo,
        "limit": limit,
    }

@app.get("/", response_class=HTMLResponse)
def index():
    return render_benchmark_page(LAST_RESULTS)

@app.get("/run", response_class=HTMLResponse)
def run():
    global LAST_RESULTS

    LAST_RESULTS = run_benchmark()

    return render_benchmark_page(LAST_RESULTS)

@app.get("/evaluation/recommendation-log/csv")
def recommendation_log_csv(
    eventType: str = "",
    pageType: str = "",
    algorithm: str = "",
    sessionId: str = "",
    customerId: str = "",
    sourceSku: str = "",
    recommendedSku: str = "",
    dateFrom: str = "",
    dateTo: str = "",
    limit: int = 5000,
):
    csv_text = fetch_recommendation_event_log_csv(
        build_recommendation_log_filters(
            eventType=eventType,
            pageType=pageType,
            algorithm=algorithm,
            sessionId=sessionId,
            customerId=customerId,
            sourceSku=sourceSku,
            recommendedSku=recommendedSku,
            dateFrom=dateFrom,
            dateTo=dateTo,
            limit=limit,
        )
    )

    return PlainTextResponse(
        csv_text,
        media_type="text/csv",
        headers={
            "Content-Disposition": "attachment; filename=recommendation_event_log.csv"
        },
    )

@app.get("/csv")
def csv_report():
    if not LAST_RESULTS:
        return PlainTextResponse(
            "No benchmark has been run yet.",
            media_type="text/plain",
        )

    return PlainTextResponse(
        rows_to_csv(LAST_RESULTS),
        media_type="text/csv",
        headers={
            "Content-Disposition": "attachment; filename=benchmark_results.csv"
        },
    )

@app.get("/evaluation", response_class=HTMLResponse)
def evaluation_page(
    eventType: str = "",
    pageType: str = "",
    algorithm: str = "",
    sessionId: str = "",
    customerId: str = "",
    sourceSku: str = "",
    recommendedSku: str = "",
    dateFrom: str = "",
    dateTo: str = "",
    limit: int = 200,
):
    report = load_latest_evaluation_report()

    try:
        config_response = requests.get(
            f"{settings.search_url.rstrip('/')}/config/all",
            headers={
                "X-API-KEY": settings.search_api_key,
            },
            timeout=10,
        )

        config_response.raise_for_status()
        config = config_response.json()

        if not isinstance(config, dict):
            config = {}
    except Exception as exc:
        print(
            "Failed to load all search configurations:",
            exc,
        )
        config = {}

    recommendation_log = fetch_recommendation_event_log_report(
        build_recommendation_log_filters(
            eventType=eventType,
            pageType=pageType,
            algorithm=algorithm,
            sessionId=sessionId,
            customerId=customerId,
            sourceSku=sourceSku,
            recommendedSku=recommendedSku,
            dateFrom=dateFrom,
            dateTo=dateTo,
            limit=limit,
        )
    )
    recommendation_metrics = fetch_recommendation_event_metrics()
    user_study_metrics = fetch_user_study_metrics()

    return render_evaluation_page(
        report,
        config=config,
        recommendation_log=recommendation_log,
        recommendation_metrics=recommendation_metrics,
        user_study_metrics=user_study_metrics,
    )

@app.post("/evaluation/update-config")
async def update_evaluation_config(
    request: Request,
):
    form = await request.form()

    method = form_text(
        form,
        "searchMethod",
        "lexical",
    )

    if method not in SEARCH_METHODS:
        method = "lexical"

    payload = {
        "name": form_text(
            form,
            "name",
            "Search configuration",
        ),
        "searchMethod": method,

        "nameWeight": form_int(
            form,
            "nameWeight",
            20,
        ),
        "descriptionWeight": form_int(
            form,
            "descriptionWeight",
            5,
        ),
        "categoryWeight": form_int(
            form,
            "categoryWeight",
            4,
        ),
        "materialWeight": form_int(
            form,
            "materialWeight",
            2,
        ),
        "colorWeight": form_int(
            form,
            "colorWeight",
            2,
        ),
        "sizeWeight": form_int(
            form,
            "sizeWeight",
            2,
        ),
        "attributesWeight": form_int(
            form,
            "attributesWeight",
            2,
        ),

        "sameCategoryBonus": form_float(
            form,
            "sameCategoryBonus",
            0.35,
        ),
        "sameMaterialBonus": form_float(
            form,
            "sameMaterialBonus",
            0.15,
        ),
        "sameColorBonus": form_float(
            form,
            "sameColorBonus",
            0.10,
        ),
        "sameSizeBonus": form_float(
            form,
            "sameSizeBonus",
            0.10,
        ),

        "sameCategoryRecommendationWeight": form_float(
            form,
            "sameCategoryRecommendationWeight",
            0.35,
        ),
        "sameColorRecommendationWeight": form_float(
            form,
            "sameColorRecommendationWeight",
            0.10,
        ),
        "sameSizeRecommendationWeight": form_float(
            form,
            "sameSizeRecommendationWeight",
            0.10,
        ),
        "wishlistRecommendationWeight": form_float(
            form,
            "wishlistRecommendationWeight",
            0.30,
        ),
        "orderHistoryRecommendationWeight": form_float(
            form,
            "orderHistoryRecommendationWeight",
            0.25,
        ),
        "searchHistoryRecommendationWeight": form_float(
            form,
            "searchHistoryRecommendationWeight",
            0.20,
        ),
        "viewHistoryRecommendationWeight": form_float(
            form,
            "viewHistoryRecommendationWeight",
            0.35,
        ),

        "maxRecommendationPerCategory": form_int(
            form,
            "maxRecommendationPerCategory",
            4,
        ),
        "recommendationDiversityPenalty": form_float(
            form,
            "recommendationDiversityPenalty",
            0.10,
        ),

        "recommendationEnabled": form_bool(
            form,
            "recommendationEnabled",
        ),
        "recommendationLoggingEnabled": form_bool(
            form,
            "recommendationLoggingEnabled",
        ),

        "algorithmSettings":
            build_algorithm_settings_from_form(
                form,
                method,
            ),
    }

    status, data, _ = request_json(
        "POST",
        (
            f"{settings.bms_url.rstrip('/')}"
            "/api/search-config/update"
        ),
        headers={
            "X-API-KEY": settings.search_api_key,
        },
        json=payload,
    )

    if status < 200 or status >= 300:
        print(
            "BMS configuration update failed:",
            status,
            data,
        )

        return RedirectResponse(
            url="/evaluation?config_error=1",
            status_code=303,
        )

    return RedirectResponse(
        url="/evaluation",
        status_code=303,
    )

@app.post("/evaluation/generate")
def generate_evaluation_report():
    report = run_evaluation()
    save_evaluation_report(report)

    return RedirectResponse(url="/evaluation", status_code=303)

@app.get("/evaluation/latest")
def latest_evaluation_report():
    return load_latest_evaluation_report()

def form_text(form, key, default=""):
    value = form.get(key)

    if value is None:
        return default

    return str(value).strip()


def form_float(form, key, default=0.0):
    try:
        return float(form.get(key, default))
    except (TypeError, ValueError):
        return float(default)


def form_int(form, key, default=0):
    try:
        return int(form.get(key, default))
    except (TypeError, ValueError):
        return int(default)


def form_bool(form, key, default=False):
    if key not in form:
        return False

    value = str(form.get(key, "")).strip().lower()

    return value in {
        "1",
        "true",
        "yes",
        "on",
    }


def build_session_settings_from_form(
    form,
    prefix,
    default_candidate_multiplier,
):
    return {
        "current_product_weight": form_float(
            form,
            f"{prefix}_session_current_product_weight",
            1.0,
        ),
        "viewed_product_weight": form_float(
            form,
            f"{prefix}_session_viewed_product_weight",
            0.70,
        ),
        "cart_product_weight": form_float(
            form,
            f"{prefix}_session_cart_product_weight",
            0.90,
        ),
        "max_viewed_seeds": form_int(
            form,
            f"{prefix}_session_max_viewed_seeds",
            5,
        ),
        "max_cart_seeds": form_int(
            form,
            f"{prefix}_session_max_cart_seeds",
            5,
        ),
        "max_total_seeds": form_int(
            form,
            f"{prefix}_session_max_total_seeds",
            8,
        ),
        "candidate_multiplier": form_int(
            form,
            f"{prefix}_session_candidate_multiplier",
            default_candidate_multiplier,
        ),
        "minimum_candidates": form_int(
            form,
            f"{prefix}_session_minimum_candidates",
            10,
        ),
    }


def build_algorithm_settings_from_form(form, method):
    if method == "semantic_vector":
        return {
            "document_fields": {
                "name": form_bool(
                    form,
                    "semantic_document_name",
                    True,
                ),
                "category": form_bool(
                    form,
                    "semantic_document_category",
                    True,
                ),
                "description": form_bool(
                    form,
                    "semantic_document_description",
                    True,
                ),
                "material": form_bool(
                    form,
                    "semantic_document_material",
                    True,
                ),
                "color": form_bool(
                    form,
                    "semantic_document_color",
                    True,
                ),
                "size": form_bool(
                    form,
                    "semantic_document_size",
                    True,
                ),
                "attributes": form_bool(
                    form,
                    "semantic_document_attributes",
                    False,
                ),
            },
            "embedding": {
                "batch_size": form_int(
                    form,
                    "semantic_embedding_batch_size",
                    32,
                ),
                "normalize_embeddings": form_bool(
                    form,
                    "semantic_normalize_embeddings",
                    True,
                ),
            },
            "reranking": {
                "semantic_similarity_weight": form_float(
                    form,
                    "semantic_similarity_weight",
                    0.75,
                ),
                "lexical_overlap_weight": form_float(
                    form,
                    "semantic_lexical_overlap_weight",
                    0.25,
                ),
                "minimum_token_length": form_int(
                    form,
                    "semantic_minimum_token_length",
                    2,
                ),
            },
            "candidate_pool": {
                "multiplier": form_int(
                    form,
                    "semantic_candidate_multiplier",
                    5,
                ),
                "minimum_candidates": form_int(
                    form,
                    "semantic_minimum_candidates",
                    50,
                ),
            },
            "vector_search": {
                "ivfflat_probes": form_int(
                    form,
                    "semantic_ivfflat_probes",
                    10,
                ),
            },
            "session_recommendation":
                build_session_settings_from_form(
                    form,
                    "semantic",
                    2,
                ),
        }

    if method == "elasticsearch_bm25":
        return {
            "search_query": {
                "type": form_text(
                    form,
                    "elastic_search_query_type",
                    "best_fields",
                ),
                "operator": form_text(
                    form,
                    "elastic_search_operator",
                    "or",
                ),
                "field_weights": {
                    "name": form_float(
                        form,
                        "elastic_search_name_weight",
                        5,
                    ),
                    "category": form_float(
                        form,
                        "elastic_search_category_weight",
                        3,
                    ),
                    "description": form_float(
                        form,
                        "elastic_search_description_weight",
                        2,
                    ),
                    "material": form_float(
                        form,
                        "elastic_search_material_weight",
                        1,
                    ),
                    "color": form_float(
                        form,
                        "elastic_search_color_weight",
                        1,
                    ),
                    "size": form_float(
                        form,
                        "elastic_search_size_weight",
                        1,
                    ),
                    "sku": form_float(
                        form,
                        "elastic_search_sku_weight",
                        2,
                    ),
                },
            },
            "recommendation_query": {
                "type": form_text(
                    form,
                    "elastic_recommendation_query_type",
                    "best_fields",
                ),
                "operator": form_text(
                    form,
                    "elastic_recommendation_operator",
                    "or",
                ),
                "field_weights": {
                    "name": form_float(
                        form,
                        "elastic_recommendation_name_weight",
                        5,
                    ),
                    "category": form_float(
                        form,
                        "elastic_recommendation_category_weight",
                        4,
                    ),
                    "description": form_float(
                        form,
                        "elastic_recommendation_description_weight",
                        2,
                    ),
                    "material": form_float(
                        form,
                        "elastic_recommendation_material_weight",
                        2,
                    ),
                    "color": form_float(
                        form,
                        "elastic_recommendation_color_weight",
                        1,
                    ),
                    "size": form_float(
                        form,
                        "elastic_recommendation_size_weight",
                        1,
                    ),
                    "sku": form_float(
                        form,
                        "elastic_recommendation_sku_weight",
                        2,
                    ),
                },
                "candidate_multiplier": form_int(
                    form,
                    "elastic_recommendation_candidate_multiplier",
                    3,
                ),
                "minimum_candidates": form_int(
                    form,
                    "elastic_recommendation_minimum_candidates",
                    20,
                ),
                "exclude_source_sku": form_bool(
                    form,
                    "elastic_recommendation_exclude_source_sku",
                    True,
                ),
            },
            "session_recommendation":
                build_session_settings_from_form(
                    form,
                    "elastic",
                    2,
                ),
        }

    return {
        "vectorizer": {
            "lowercase": form_bool(
                form,
                "lexical_lowercase",
                True,
            ),
            "ngram_range": [
                form_int(
                    form,
                    "lexical_ngram_min",
                    1,
                ),
                form_int(
                    form,
                    "lexical_ngram_max",
                    2,
                ),
            ],
            "n_features": form_int(
                form,
                "lexical_n_features",
                262144,
            ),
            "alternate_sign": form_bool(
                form,
                "lexical_alternate_sign",
                False,
            ),
            "normalization": form_text(
                form,
                "lexical_normalization",
                "l2",
            ),
            "token_pattern": form_text(
                form,
                "lexical_token_pattern",
                r"\b\w+\b",
            ),
        },
        "candidate_filter": {
            "minimum_query_token_matches": form_int(
                form,
                "lexical_candidate_minimum_query_token_matches",
                1,
            ),
            "fallback_to_all_documents": form_bool(
                form,
                "lexical_fallback_to_all_documents",
                True,
            ),
        },
        "partial_match": {
            "require_all_query_tokens": form_bool(
                form,
                "lexical_require_all_query_tokens",
                True,
            ),
            "minimum_query_token_matches": form_int(
                form,
                "lexical_partial_minimum_query_token_matches",
                1,
            ),
            "base_score": form_float(
                form,
                "lexical_partial_base_score",
                1.0,
            ),
            "merge_bonus_weight": form_float(
                form,
                "lexical_partial_merge_bonus_weight",
                0.20,
            ),
        },
        "session_recommendation":
            build_session_settings_from_form(
                form,
                "lexical",
                3,
            ),
    }