from fastapi import FastAPI
from fastapi.responses import HTMLResponse, PlainTextResponse, RedirectResponse
from fastapi import Form
from benchmark_service import run_benchmark, rows_to_csv
from evaluation_service import run_evaluation
from html_pages import render_benchmark_page, render_evaluation_page
from report_storage import save_evaluation_report, load_latest_evaluation_report
import requests
from config import settings

app = FastAPI(title="E-shop Search Benchmark")

LAST_RESULTS = []


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
def evaluation_page():
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

    return render_evaluation_page(report, config=config)

@app.post("/evaluation/update-config")
def update_config(
    nameWeight: int = Form(...),
    descriptionWeight: int = Form(...),
    categoryWeight: int = Form(...),
    materialWeight: int = Form(...),
    colorWeight: int = Form(...),
    sizeWeight: int = Form(...),
    sameCategoryRecommendationWeight: float = Form(...),
    sameColorRecommendationWeight: float = Form(...),
    sameSizeRecommendationWeight: float = Form(...),
):
    payload = {
        "nameWeight": nameWeight,
        "descriptionWeight": descriptionWeight,
        "categoryWeight": categoryWeight,
        "materialWeight": materialWeight,
        "colorWeight": colorWeight,
        "sizeWeight": sizeWeight,
        "sameCategoryRecommendationWeight": sameCategoryRecommendationWeight,
        "sameColorRecommendationWeight": sameColorRecommendationWeight,
        "sameSizeRecommendationWeight": sameSizeRecommendationWeight,
    }

    r = requests.post(
        f"{settings.bms_url.rstrip('/')}/api/search-config/update",
        json=payload,
        headers={
            "X-API-KEY": settings.search_api_key,
        },
        timeout=30,
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