import csv
import io
import os
import statistics
import time
from datetime import datetime

import requests
from fastapi import FastAPI
from fastapi.responses import HTMLResponse, PlainTextResponse, RedirectResponse

import json
import psycopg2
from pathlib import Path

SEARCH_URL = os.getenv("SEARCH_URL", "http://search-service:8000").rstrip("/")
BMS_URL = os.getenv("BMS_URL", "http://bms").rstrip("/")

QUERIES = [
    "laptop",
    "smartphone",
    "keyboard",
    "mouse",
    "headphones",
    "shirt",
    "jacket",
    "cotton",
    "black",
    "white",
]

LAST_RESULTS = []

app = FastAPI(title="E-shop Search Benchmark")


def request_json(method: str, url: str, **kwargs):
    start = time.perf_counter()

    headers = kwargs.pop("headers", {})
    headers["X-BENCHMARK"] = "1"

    try:
        if method == "POST":
            response = requests.post(
                url,
                timeout=10,
                headers=headers,
                **kwargs,
            )
        else:
            response = requests.get(
                url,
                timeout=10,
                headers=headers,
                **kwargs,
            )

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
                <a href="/evaluation">Evaluation report</a>
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

DB_HOST = os.getenv("DB_HOST", "db")
DB_PORT = int(os.getenv("DB_PORT", "5432"))
DB_NAME = os.getenv("DB_NAME", "app")
DB_USER = os.getenv("DB_USER", "user")
DB_PASSWORD = os.getenv("DB_PASSWORD", "password")

REPORT_DIR = Path("/app/reports")
REPORT_DIR.mkdir(parents=True, exist_ok=True)


def get_db_connection():
    return psycopg2.connect(
        host=DB_HOST,
        port=DB_PORT,
        dbname=DB_NAME,
        user=DB_USER,
        password=DB_PASSWORD,
    )


def evaluate_search(limit: int = 10):
    rows = []

    for query in QUERIES:
        status, data, elapsed_ms = request_json(
            "POST",
            f"{SEARCH_URL}/search",
            json={
                "query": query,
                "limit": limit,
            },
        )

        results = data.get("results", []) if status == 200 else []

        rows.append({
            "query": query,
            "status": status,
            "response_time_ms": elapsed_ms,
            "result_count": len(results),
            "has_results": len(results) > 0,
        })

    successful = [r for r in rows if r["status"] == 200]
    with_results = [r for r in successful if r["has_results"]]

    return {
        "queries_evaluated": len(rows),
        "successful_queries": len(successful),
        "queries_with_results": len(with_results),
        "result_hit_rate": len(with_results) / len(rows) if rows else 0,
        "avg_response_time_ms": (
            statistics.mean(r["response_time_ms"] for r in successful)
            if successful else 0
        ),
        "details": rows,
    }


def fetch_customers_with_behavior():
    conn = get_db_connection()
    cur = conn.cursor()

    cur.execute("""
        SELECT DISTINCT customer_id
        FROM customer_product_view_log
        WHERE customer_id IS NOT NULL

        UNION

        SELECT DISTINCT customer_id
        FROM customer_search_log
        WHERE customer_id IS NOT NULL

        UNION

        SELECT DISTINCT o.customer_id
        FROM orders o
        WHERE o.customer_id IS NOT NULL
    """)

    rows = cur.fetchall()

    cur.close()
    conn.close()

    return [
        {"id": row[0]}
        for row in rows
        if row[0] is not None
    ]


def fetch_user_recent_viewed_skus(customer_id: int, limit: int = 10):
    conn = get_db_connection()
    cur = conn.cursor()

    cur.execute("""
        SELECT sku
        FROM customer_product_view_log
        WHERE customer_id = %s
          AND sku IS NOT NULL
          AND sku <> ''
        ORDER BY viewed_at DESC
        LIMIT %s
    """, (customer_id, limit))

    rows = cur.fetchall()

    cur.close()
    conn.close()

    skus = []

    for row in rows:
        sku = str(row[0]).strip()

        if sku and sku not in skus:
            skus.append(sku)

    return skus


def fetch_categories_for_skus(skus: list[str]):
    if not skus:
        return []

    conn = get_db_connection()
    cur = conn.cursor()

    cur.execute("""
        SELECT DISTINCT p.sku, c.name
        FROM product p
        LEFT JOIN category c ON p.category_id = c.id
        INNER JOIN (
            SELECT sku, MAX(id) AS max_id
            FROM product
            WHERE sku = ANY(%s)
            GROUP BY sku
        ) latest ON latest.max_id = p.id
        WHERE p.sku = ANY(%s)
    """, (skus, skus))

    rows = cur.fetchall()

    cur.close()
    conn.close()

    categories = []

    for _, category in rows:
        category = str(category or "").strip()

        if category and category not in categories:
            categories.append(category)

    return categories


def call_recommend_api(sku: str, limit: int = 10):
    status, data, _ = request_json(
        "GET",
        f"{SEARCH_URL}/recommend/{sku}",
        params={"limit": limit},
    )

    if status != 200:
        return []

    results = data.get("results", [])

    return results if isinstance(results, list) else []


def save_evaluation_report(report: dict):
    timestamp = datetime.utcnow().strftime("%Y%m%d_%H%M%S")

    latest_path = REPORT_DIR / "evaluation_latest.json"
    dated_path = REPORT_DIR / f"evaluation_{timestamp}.json"

    text = json.dumps(report, indent=2, ensure_ascii=False)

    latest_path.write_text(text, encoding="utf-8")
    dated_path.write_text(text, encoding="utf-8")


def load_latest_evaluation_report():
    latest_path = REPORT_DIR / "evaluation_latest.json"

    if not latest_path.exists():
        return {
            "error": "No evaluation report has been generated yet."
        }

    return json.loads(latest_path.read_text(encoding="utf-8"))

def run_evaluation():
    search_eval = evaluate_search()
    recommendation_eval = evaluate_recommendations()

    return {
        "generated_at": datetime.utcnow().isoformat() + "Z",
        "search_evaluation": search_eval,
        "recommendation_evaluation": recommendation_eval,
    }

def evaluate_recommendations(limit: int = 10):
    users = fetch_customers_with_behavior()

    total = 0
    category_hits = 0
    sku_hits = 0
    diversity_scores = []

    for user in users:
        seed_skus = fetch_user_recent_viewed_skus(user["id"])

        if not seed_skus:
            continue

        target_categories = fetch_categories_for_skus(seed_skus)

        recommended = []

        for sku in seed_skus[:3]:
            recommended.extend(call_recommend_api(sku, limit))

        recommended_skus = list(dict.fromkeys(
            item["product_sku"]
            for item in recommended
            if item.get("product_sku")
        ))[:limit]

        if not recommended_skus:
            continue

        recommended_categories = fetch_categories_for_skus(recommended_skus)

        total += 1

        if set(seed_skus) & set(recommended_skus):
            sku_hits += 1

        if set(target_categories) & set(recommended_categories):
            category_hits += 1

        diversity_scores.append(len(set(recommended_categories)))

    return {
        "evaluated_users": total,
        "sku_hit_rate": sku_hits / total if total else 0,
        "category_hit_rate": category_hits / total if total else 0,
        "avg_category_diversity": (
            sum(diversity_scores) / len(diversity_scores)
            if diversity_scores else 0
        ),
    }

@app.post("/evaluation/generate")
def generate_evaluation_report():
    report = run_evaluation()
    save_evaluation_report(report)
    return RedirectResponse(url="/evaluation", status_code=303)

@app.get("/evaluation/latest")
def latest_evaluation_report():
    return load_latest_evaluation_report()

@app.get("/evaluation", response_class=HTMLResponse)
def evaluation_page():
    report = load_latest_evaluation_report()

    if "error" in report:
        body = "<p>No evaluation report has been generated yet.</p>"
    else:
        search = report.get("search_evaluation", {})
        rec = report.get("recommendation_evaluation", {})

        body = f"""
        <h2>Search Evaluation</h2>
        <ul>
            <li>Queries evaluated: {search.get("queries_evaluated", 0)}</li>
            <li>Result hit rate: {search.get("result_hit_rate", 0):.2f}</li>
            <li>Average response time: {search.get("avg_response_time_ms", 0):.2f} ms</li>
        </ul>

        <h2>Recommendation Evaluation</h2>
        <ul>
            <li>Evaluated users: {rec.get("evaluated_users", 0)}</li>
            <li>SKU hit rate: {rec.get("sku_hit_rate", 0):.2f}</li>
            <li>Category hit rate: {rec.get("category_hit_rate", 0):.2f}</li>
            <li>Average category diversity: {rec.get("avg_category_diversity", 0):.2f}</li>
        </ul>
        """

    return f"""
    <html>
    <head>
        <title>Search Evaluation Report</title>
    </head>
    <body style="font-family: Arial; margin: 40px;">
        <h1>Search & Recommendation Evaluation</h1>

        <p>
            <a href="/">Back to benchmark</a>
        </p>

        <form method="post" action="/evaluation/generate">
            <button type="submit">Generate evaluation report</button>
        </form>

        <hr>

        {body}
    </body>
    </html>
    """