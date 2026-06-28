import statistics
import json
from datetime import datetime

from config import settings
from html_common import page_style


def build_benchmark_chart_data(rows):
    grouped = {}

    for row in rows:
        if row["type"] in ["tfidf_cold", "semantic_vector_cold"]:
            continue

        query = row["query"]

        if query not in grouped:
            grouped[query] = {
                "tfidf": 0,
                "semantic_vector": 0,
                "sql_like": 0,
            }

        if row["type"] in grouped[query]:
            grouped[query][row["type"]] = round(row["response_time_ms"], 2)

    return {
        "labels": list(grouped.keys()),
        "tfidf": [item["tfidf"] for item in grouped.values()],
        "semantic_vector": [item["semantic_vector"] for item in grouped.values()],
        "sql_like": [item["sql_like"] for item in grouped.values()],
    }


def build_comparison_rows(rows):
    grouped = {}

    for row in rows:
        if row["type"] in ["tfidf_cold", "semantic_vector_cold"]:
            continue

        query = row["query"]

        if query not in grouped:
            grouped[query] = {
                "tfidf_ms": None,
                "semantic_ms": None,
                "sql_ms": None,
                "tfidf_count": None,
                "semantic_count": None,
                "sql_count": None,
                "tfidf_status": None,
                "semantic_status": None,
                "sql_status": None,
            }

        if row["type"] == "tfidf":
            grouped[query]["tfidf_ms"] = row["response_time_ms"]
            grouped[query]["tfidf_count"] = row["result_count"]
            grouped[query]["tfidf_status"] = row["status"]

        if row["type"] == "semantic_vector":
            grouped[query]["semantic_ms"] = row["response_time_ms"]
            grouped[query]["semantic_count"] = row["result_count"]
            grouped[query]["semantic_status"] = row["status"]

        if row["type"] == "sql_like":
            grouped[query]["sql_ms"] = row["response_time_ms"]
            grouped[query]["sql_count"] = row["result_count"]
            grouped[query]["sql_status"] = row["status"]

    table_rows = ""

    for query, data in grouped.items():
        times = {
            "TF-IDF": data["tfidf_ms"],
            "Semantic Vector": data["semantic_ms"],
            "SQL LIKE": data["sql_ms"],
        }

        valid_times = {
            name: value
            for name, value in times.items()
            if value is not None
        }

        fastest = min(valid_times, key=valid_times.get) if valid_times else "-"

        table_rows += f"""
        <tr>
            <td>{query}</td>
            <td>{f'{data["tfidf_ms"]:.2f} ms' if data["tfidf_ms"] is not None else '-'}</td>
            <td>{f'{data["semantic_ms"]:.2f} ms' if data["semantic_ms"] is not None else '-'}</td>
            <td>{f'{data["sql_ms"]:.2f} ms' if data["sql_ms"] is not None else '-'}</td>
            <td>{data["tfidf_count"]}</td>
            <td>{data["semantic_count"]}</td>
            <td>{data["sql_count"]}</td>
            <td>{fastest}</td>
            <td>{data["tfidf_status"]}</td>
            <td>{data["semantic_status"]}</td>
            <td>{data["sql_status"]}</td>
        </tr>
        """

    return table_rows


def build_summary(rows):
    tfidf_rows = [
        row for row in rows
        if row["type"] == "tfidf" and row["status"] == 200
    ]

    semantic_rows = [
        row for row in rows
        if row["type"] == "semantic_vector" and row["status"] == 200
    ]

    sql_rows = [
        row for row in rows
        if row["type"] == "sql_like" and row["status"] == 200
    ]

    tfidf_cold_rows = [
        row for row in rows
        if row["type"] == "tfidf_cold" and row["status"] == 200
    ]

    semantic_cold_rows = [
        row for row in rows
        if row["type"] == "semantic_vector_cold" and row["status"] == 200
    ]

    if not tfidf_rows or not semantic_rows or not sql_rows:
        return """
        <div class="summary">
            <div class="metric-card warning">No complete benchmark data available.</div>
        </div>
        """

    tfidf_avg = statistics.mean(row["response_time_ms"] for row in tfidf_rows)
    semantic_avg = statistics.mean(row["response_time_ms"] for row in semantic_rows)
    sql_avg = statistics.mean(row["response_time_ms"] for row in sql_rows)

    tfidf_cold_avg = (
        statistics.mean(row["response_time_ms"] for row in tfidf_cold_rows)
        if tfidf_cold_rows else 0.0
    )

    semantic_cold_avg = (
        statistics.mean(row["response_time_ms"] for row in semantic_cold_rows)
        if semantic_cold_rows else 0.0
    )

    averages = {
        "TF-IDF": tfidf_avg,
        "Semantic Vector": semantic_avg,
        "SQL LIKE": sql_avg,
    }

    fastest = min(averages, key=averages.get)

    return f"""
    <div class="summary">
        <div class="metric-card">
            <span>TF-IDF avg</span>
            <strong>{tfidf_avg:.2f} ms</strong>
        </div>
        <div class="metric-card">
            <span>Semantic vector avg</span>
            <strong>{semantic_avg:.2f} ms</strong>
        </div>
        <div class="metric-card">
            <span>SQL LIKE avg</span>
            <strong>{sql_avg:.2f} ms</strong>
        </div>
        <div class="metric-card">
            <span>TF-IDF cold start</span>
            <strong>{tfidf_cold_avg:.2f} ms</strong>
        </div>
        <div class="metric-card">
            <span>Semantic cold start</span>
            <strong>{semantic_cold_avg:.2f} ms</strong>
        </div>
        <div class="metric-card result">
            <span>Fastest method</span>
            <strong>{fastest}</strong>
        </div>
    </div>
    """


def render_benchmark_page(rows):
    if not rows:
        summary_html = ""
        table_rows = """
        <tr>
            <td colspan="11">
                No benchmark has been run yet. Click "Run benchmark again" to start.
            </td>
        </tr>
        """
    else:
        summary_html = build_summary(rows)
        table_rows = build_comparison_rows(rows)

    generated_at = datetime.now().strftime("%Y-%m-%d %H:%M:%S")

    chart_data = build_benchmark_chart_data(rows) if rows else {
        "labels": [],
        "tfidf": [],
        "semantic_vector": [],
        "sql_like": [],
    }

    return f"""
    <!doctype html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>Search Benchmark Report</title>
        {page_style()}
    </head>
    <body>
        <div class="page">
            <div class="card">
                <h1>Search Benchmark Report</h1>

                <div class="meta">
                    Page generated at: {generated_at}<br>
                    Search service: {settings.search_url}<br>
                    SQL LIKE endpoint: {settings.bms_url}/search-like
                </div>

                <div class="actions">
                    <a href="/run">Run benchmark again</a>
                    <a href="/csv" class="secondary">Download CSV</a>
                    <a href="/evaluation" class="secondary">Evaluation report</a>
                </div>

                {summary_html}

                <div class="chart-card">
                    <h2>Response Time Comparison</h2>
                    <canvas id="benchmarkChart"></canvas>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>Query</th>
                            <th>TF-IDF avg time</th>
                            <th>Semantic avg time</th>
                            <th>SQL LIKE avg time</th>
                            <th>TF-IDF results</th>
                            <th>Semantic results</th>
                            <th>SQL LIKE results</th>
                            <th>Fastest</th>
                            <th>TF-IDF status</th>
                            <th>Semantic status</th>
                            <th>SQL LIKE status</th>
                        </tr>
                    </thead>
                    <tbody>
                        {table_rows}
                    </tbody>
                </table>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            const benchmarkChartData = {json.dumps(chart_data)};

            if (benchmarkChartData.labels.length > 0) {{
                new Chart(document.getElementById('benchmarkChart'), {{
                    type: 'bar',
                    data: {{
                        labels: benchmarkChartData.labels,
                        datasets: [
                            {{
                                label: 'TF-IDF',
                                data: benchmarkChartData.tfidf
                            }},
                            {{
                                label: 'Semantic Vector',
                                data: benchmarkChartData.semantic_vector
                            }},
                            {{
                                label: 'SQL LIKE',
                                data: benchmarkChartData.sql_like
                            }}
                        ]
                    }},
                    options: {{
                        responsive: true,
                        plugins: {{
                            legend: {{
                                position: 'top'
                            }}
                        }},
                        scales: {{
                            y: {{
                                beginAtZero: true,
                                title: {{
                                    display: true,
                                    text: 'Milliseconds'
                                }}
                            }}
                        }}
                    }}
                }});
            }}
        </script>
    </body>
    </html>
    """