from fastapi import FastAPI
from fastapi.responses import HTMLResponse, PlainTextResponse, RedirectResponse

from benchmark_service import run_benchmark, rows_to_csv
from evaluation_service import run_evaluation
from html_pages import render_benchmark_page, render_evaluation_page
from report_storage import save_evaluation_report, load_latest_evaluation_report

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
    return render_evaluation_page(report)


@app.post("/evaluation/generate")
def generate_evaluation_report():
    report = run_evaluation()
    save_evaluation_report(report)

    return RedirectResponse(url="/evaluation", status_code=303)


@app.get("/evaluation/latest")
def latest_evaluation_report():
    return load_latest_evaluation_report()