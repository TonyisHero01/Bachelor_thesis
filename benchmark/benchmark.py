import csv
import io
import os
import statistics
import time
from datetime import datetime

import requests
from fastapi import FastAPI
from fastapi.responses import HTMLResponse, PlainTextResponse


SEARCH_URL = os.getenv("SEARCH_URL", "http://search-service:8000").rstrip("/")
BMS_URL = os.getenv("BMS_URL", "http://bms").rstrip("/")

QUERIES = [
    "alpha",
    "smart",
    "pro product",
    "omega",
    "basic item",
]

LAST_RESULTS = []

app = FastAPI(title="E-shop Search Benchmark")


def request_json(method: str, url: str, **kwargs):
    start = time.perf_counter()

    if method == "POST":
        response = requests.post(url, timeout=10, **kwargs)
    else:
        response = requests.get(url, timeout=10, **kwargs)

    elapsed_ms = (time.perf_counter() - start) * 1000

    try:
        data = response.json()
    except Exception:
        data = {"error": response.text[:500]}

    return response.status_code, data, elapsed_ms


def run_benchmark():
    rows = []

    status, data, elapsed = request_json(
        "POST",
        f"{SEARCH_URL}/search",
        json={"query": QUERIES[0], "limit": 10},
    )

    rows.append({
        "type": "vector_cold",
        "query": QUERIES[0],
        "response_time_ms": elapsed,
        "result_count": len(data.get("results", [])) if status == 200 else 0,
        "status": status,
    })

    for query in QUERIES:
        vector_times = []
        vector_count = 0
        vector_status = 200

        for _ in range(5):
            status, data, elapsed = request_json(
                "POST",
                f"{SEARCH_URL}/search",
                json={"query": query, "limit": 10},
            )
            vector_times.append(elapsed)
            vector_status = status
            vector_count = len(data.get("results", [])) if status == 200 else 0

        rows.append({
            "type": "vector",
            "query": query,
            "response_time_ms": statistics.mean(vector_times),
            "result_count": vector_count,
            "status": vector_status,
        })

        sql_times = []
        sql_count = 0
        sql_status = 200

        for _ in range(5):
            status, data, elapsed = request_json(
                "GET",
                f"{BMS_URL}/search-like",
                params={"q": query},
            )
            sql_times.append(elapsed)
            sql_status = status
            sql_count = len(data.get("results", [])) if status == 200 else 0

        rows.append({
            "type": "sql_like",
            "query": query,
            "response_time_ms": statistics.mean(sql_times),
            "result_count": sql_count,
            "status": sql_status,
        })

    return rows


def rows_to_csv(rows):
    output = io.StringIO()
    writer = csv.DictWriter(
        output,
        fieldnames=["type", "query", "response_time_ms", "result_count", "status"],
    )
    writer.writeheader()
    writer.writerows(rows)
    return output.getvalue()


@app.get("/", response_class=HTMLResponse)
def index():
    global LAST_RESULTS

    if not LAST_RESULTS:
        LAST_RESULTS = run_benchmark()

    generated_at = datetime.now().strftime("%Y-%m-%d %H:%M:%S")

    table_rows = ""

    for row in LAST_RESULTS:
        table_rows += f"""
        <tr>
            <td>{row["type"]}</td>
            <td>{row["query"]}</td>
            <td>{row["response_time_ms"]:.2f}</td>
            <td>{row["result_count"]}</td>
            <td>{row["status"]}</td>
        </tr>
        """

    return f"""
    <!doctype html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>Search Benchmark Report</title>
        <style>
            body {{
                font-family: Arial, sans-serif;
                margin: 40px;
                background: #f7f7f7;
            }}
            .card {{
                background: white;
                padding: 24px;
                border-radius: 14px;
                box-shadow: 0 6px 20px rgba(0,0,0,.08);
                max-width: 1000px;
                margin: auto;
            }}
            h1 {{
                margin-top: 0;
            }}
            table {{
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
            }}
            th, td {{
                border-bottom: 1px solid #ddd;
                padding: 10px;
                text-align: left;
            }}
            th {{
                background: #f0f0f0;
            }}
            .actions {{
                margin-top: 20px;
            }}
            a, button {{
                display: inline-block;
                padding: 10px 14px;
                border-radius: 8px;
                background: #222;
                color: white;
                text-decoration: none;
                border: 0;
                cursor: pointer;
                margin-right: 8px;
            }}
            .meta {{
                color: #666;
                margin-bottom: 16px;
            }}
        </style>
    </head>
    <body>
        <div class="card">
            <h1>Search Benchmark Report</h1>
            <div class="meta">
                Generated at: {generated_at}<br>
                Search service: {SEARCH_URL}<br>
                SQL LIKE endpoint: {BMS_URL}/search-like
            </div>

            <div class="actions">
                <a href="/run">Run benchmark again</a>
                <a href="/csv">Download CSV</a>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Query</th>
                        <th>Avg response time (ms)</th>
                        <th>Result count</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    {table_rows}
                </tbody>
            </table>
        </div>
    </body>
    </html>
    """


@app.get("/run")
def run():
    global LAST_RESULTS
    LAST_RESULTS = run_benchmark()
    return HTMLResponse(
        """
        <html>
        <head>
            <meta http-equiv="refresh" content="0;url=/" />
        </head>
        <body>Redirecting...</body>
        </html>
        """
    )


@app.get("/csv")
def csv_report():
    global LAST_RESULTS

    if not LAST_RESULTS:
        LAST_RESULTS = run_benchmark()

    return PlainTextResponse(
        rows_to_csv(LAST_RESULTS),
        media_type="text/csv",
        headers={"Content-Disposition": "attachment; filename=benchmark_results.csv"},
    )