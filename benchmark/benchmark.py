from fastapi import FastAPI
from fastapi.responses import HTMLResponse, PlainTextResponse, RedirectResponse
from fastapi import Form
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

    return """
    <html>
    <head>
        <meta http-equiv="refresh" content="0;url=/" />
    </head>
    <body>Redirecting...</body>
    </html>
    """

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
        config = requests.get(
            f"{settings.search_url}/config",
            headers={
                "X-API-KEY": settings.search_api_key,
            },
            timeout=10,
        ).json()
    except Exception:
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
def update_config(
    nameWeight: int = Form(...),
    descriptionWeight: int = Form(...),
    categoryWeight: int = Form(...),
    materialWeight: int = Form(...),
    colorWeight: int = Form(...),
    sizeWeight: int = Form(...),
    attributesWeight: int = Form(...),

    lexicalRecommendationWeight: float = Form(...),
    sameCategoryRecommendationWeight: float = Form(...),
    sameColorRecommendationWeight: float = Form(...),
    sameSizeRecommendationWeight: float = Form(...),
    wishlistRecommendationWeight: float = Form(...),
    orderHistoryRecommendationWeight: float = Form(...),
    searchHistoryRecommendationWeight: float = Form(...),
    viewHistoryRecommendationWeight: float = Form(...),
    maxRecommendationPerCategory: int = Form(...),
    recommendationDiversityPenalty: float = Form(...),
):
    payload = {
        "nameWeight": nameWeight,
        "descriptionWeight": descriptionWeight,
        "categoryWeight": categoryWeight,
        "materialWeight": materialWeight,
        "colorWeight": colorWeight,
        "sizeWeight": sizeWeight,
        "attributesWeight": attributesWeight,

        "lexicalRecommendationWeight": lexicalRecommendationWeight,
        "sameCategoryRecommendationWeight": sameCategoryRecommendationWeight,
        "sameColorRecommendationWeight": sameColorRecommendationWeight,
        "sameSizeRecommendationWeight": sameSizeRecommendationWeight,
        "wishlistRecommendationWeight": wishlistRecommendationWeight,
        "orderHistoryRecommendationWeight": orderHistoryRecommendationWeight,
        "searchHistoryRecommendationWeight": searchHistoryRecommendationWeight,
        "viewHistoryRecommendationWeight": viewHistoryRecommendationWeight,
        "maxRecommendationPerCategory": maxRecommendationPerCategory,
        "recommendationDiversityPenalty": recommendationDiversityPenalty,
    }

    r = requests.post(
        f"{settings.bms_url.rstrip('/')}/api/search-config/update",
        json=payload,
        headers={"X-API-KEY": settings.search_api_key},
        timeout=5,
    )

    if r.status_code >= 400:
        return HTMLResponse(r.text, status_code=500)

    return RedirectResponse(url="/evaluation", status_code=303)

@app.post("/evaluation/generate")
def generate_evaluation_report():
    report = run_evaluation()
    save_evaluation_report(report)

    return RedirectResponse(url="/evaluation", status_code=303)

@app.get("/evaluation/latest")
def latest_evaluation_report():
    return load_latest_evaluation_report()