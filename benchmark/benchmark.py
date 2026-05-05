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

    try:
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

    except Exception as exc:
        elapsed_ms = (time.perf_counter() - start) * 1000
        return 0, {"error": str(exc)}, elapsed_ms


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


def build_comparison_rows(rows):
    grouped = {}

    for row in rows:
        if row["type"] == "vector_cold":
            continue

        query = row["query"]

        if query not in grouped:
            grouped[query] = {
                "vector_ms": None,
                "sql_ms": None,
                "vector_count": None,
                "sql_count": None,
                "vector_status": None,
                "sql_status": None,
            }

        if row["type"] == "vector":
            grouped[query]["vector_ms"] = row["response_time_ms"]
            grouped[query]["vector_count"] = row["result_count"]
            grouped[query]["vector_status"] = row["status"]

        if row["type"] == "sql_like":
            grouped[query]["sql_ms"] = row["response_time_ms"]
            grouped[query]["sql_count"] = row["result_count"]
            grouped[query]["sql_status"] = row["status"]

    table_rows = ""

    for query, data in grouped.items():
        vector_ms = data["vector_ms"]
        sql_ms = data["sql_ms"]

        vector_ms_text = f"{vector_ms:.2f}" if vector_ms is not None else "-"
        sql_ms_text = f"{sql_ms:.2f}" if sql_ms is not None else "-"

        if vector_ms is not None and sql_ms is not None:
            faster = "Vector" if vector_ms < sql_ms else "SQL LIKE"
            difference = abs(vector_ms - sql_ms)
            difference_text = f"{difference:.2f} ms"
        else:
            faster = "-"
            difference_text = "-"

        table_rows += f"""
        <tr>
            <td>{query}</td>
            <td>{vector_ms_text}</td>
            <td>{sql_ms_text}</td>
            <td>{data["vector_count"] if data["vector_count"] is not None else "-"}</td>
            <td>{data["sql_count"] if data["sql_count"] is not None else "-"}</td>
            <td>{faster}</td>
            <td>{difference_text}</td>
            <td>{data["vector_status"] if data["vector_status"] is not None else "-"}</td>
            <td>{data["sql_status"] if data["sql_status"] is not None else "-"}</td>
        </tr>
        """

    return table_rows


def build_summary(rows):
    vector_rows = [r for r in rows if r["type"] == "vector" and r["status"] == 200]
    sql_rows = [r for r in rows if r["type"] == "sql_like" and r["status"] == 200]
    cold_rows = [r for r in rows if r["type"] == "vector_cold" and r["status"] == 200]

    if not vector_rows or not sql_rows:
        return """
        <div class="summary">
            <div class="summary-box">No complete benchmark data available.</div>
        </div>
        """

    vector_avg = statistics.mean(r["response_time_ms"] for r in vector_rows)
    sql_avg = statistics.mean(r["response_time_ms"] for r in sql_rows)
    cold_avg = statistics.mean(r["response_time_ms"] for r in cold_rows) if cold_rows else 0.0

    if vector_avg < sql_avg:
        faster_text = f"Vector search is {(sql_avg / vector_avg):.2f}× faster on average."
    else:
        faster_text = f"SQL LIKE is {(vector_avg / sql_avg):.2f}× faster on average."

    return f"""
    <div class="summary">
        <div class="summary-box">
            <strong>Vector avg</strong><br>
            {vector_avg:.2f} ms
        </div>
        <div class="summary-box">
            <strong>SQL LIKE avg</strong><br>
            {sql_avg:.2f} ms
        </div>
        <div class="summary-box">
            <strong>Vector cold start</strong><br>
            {cold_avg:.2f} ms
        </div>
        <div class="summary-box">
            <strong>Result</strong><br>
            {faster_text}
        </div>
    </div>
    """


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
    if not LAST_RESULTS:
        summary_html = ""
        table_rows = """
        <tr>
            <td colspan="9">No benchmark has been run yet. Click "Run benchmark again" to start.</td>
        </tr>
        """
    else:
        summary_html = build_summary(LAST_RESULTS)
        table_rows = build_comparison_rows(LAST_RESULTS)

    generated_at = datetime.now().strftime("%Y-%m-%d %H:%M:%S")

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
                max-width: 1200px;
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
            a {{
                display: inline-block;
                padding: 10px 14px;
                border-radius: 8px;
                background: #222;
                color: white;
                text-decoration: none;
                margin-right: 8px;
            }}
            .meta {{
                color: #666;
                margin-bottom: 16px;
            }}
            .summary {{
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
                gap: 14px;
                margin-top: 20px;
            }}
            .summary-box {{
                background: #f3f3f3;
                border-radius: 12px;
                padding: 16px;
                line-height: 1.5;
            }}
        </style>
    </head>
    <body>
        <div class="card">
            <h1>Search Benchmark Report</h1>

            <div class="meta">
                Page generated at: {generated_at}<br>
                Search service: {SEARCH_URL}<br>
                SQL LIKE endpoint: {BMS_URL}/search-like
            </div>

            <div class="actions">
                <a href="/run">Run benchmark again</a>
                <a href="/csv">Download CSV</a>
            </div>

            {summary_html}

            <table>
                <thead>
                    <tr>
                        <th>Query</th>
                        <th>Vector avg time (ms)</th>
                        <th>SQL LIKE avg time (ms)</th>
                        <th>Vector result count</th>
                        <th>SQL LIKE result count</th>
                        <th>Faster method</th>
                        <th>Difference</th>
                        <th>Vector status</th>
                        <th>SQL LIKE status</th>
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
        headers={"Content-Disposition": "attachment; filename=benchmark_results.csv"},
    )