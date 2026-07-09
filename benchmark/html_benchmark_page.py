import statistics
import json
from datetime import datetime

from config import settings
from html_common import page_style

def build_benchmark_chart_data(rows):
    grouped = {}

    for row in rows:
        if row["type"] in [
            "lexical_cold",
            "semantic_vector_cold",
            "elasticsearch_bm25_cold",
        ]:
            continue

        query = row["query"]

        if query not in grouped:
            grouped[query] = {
                "lexical": 0,
                "semantic_vector": 0,
                "elasticsearch_bm25": 0,
            }

        if row["type"] in grouped[query]:
            grouped[query][row["type"]] = round(row["response_time_ms"], 2)

    return {
        "labels": list(grouped.keys()),
        "lexical": [item["lexical"] for item in grouped.values()],
        "semantic_vector": [item["semantic_vector"] for item in grouped.values()],
        "elasticsearch_bm25": [item["elasticsearch_bm25"] for item in grouped.values()],
    }

def build_comparison_rows(rows):
    grouped = {}

    for row in rows:
        if row["type"] in [
            "lexical_cold",
            "semantic_vector_cold",
            "elasticsearch_bm25_cold",
        ]:
            continue

        query = row["query"]

        if query not in grouped:
            grouped[query] = {
                "lexical_ms": None,
                "semantic_ms": None,
                "elastic_ms": None,
                "lexical_count": None,
                "semantic_count": None,
                "elastic_count": None,
                "lexical_status": None,
                "semantic_status": None,
                "elastic_status": None,
            }

        if row["type"] == "lexical":
            grouped[query]["lexical_ms"] = row["response_time_ms"]
            grouped[query]["lexical_count"] = row["result_count"]
            grouped[query]["lexical_status"] = row["status"]

        if row["type"] == "semantic_vector":
            grouped[query]["semantic_ms"] = row["response_time_ms"]
            grouped[query]["semantic_count"] = row["result_count"]
            grouped[query]["semantic_status"] = row["status"]

        if row["type"] == "elasticsearch_bm25":
            grouped[query]["elastic_ms"] = row["response_time_ms"]
            grouped[query]["elastic_count"] = row["result_count"]
            grouped[query]["elastic_status"] = row["status"]

    table_rows = ""

    for query, data in grouped.items():
        times = {
            "Lexical": data["lexical_ms"],
            "Semantic Vector": data["semantic_ms"],
            "Elasticsearch BM25": data["elastic_ms"],
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
            <td>{f'{data["lexical_ms"]:.2f} ms' if data["lexical_ms"] is not None else '-'}</td>
            <td>{f'{data["semantic_ms"]:.2f} ms' if data["semantic_ms"] is not None else '-'}</td>
            <td>{f'{data["elastic_ms"]:.2f} ms' if data["elastic_ms"] is not None else '-'}</td>
            <td>{data["lexical_count"]}</td>
            <td>{data["semantic_count"]}</td>
            <td>{data["elastic_count"]}</td>
            <td>{fastest}</td>
            <td>{data["lexical_status"]}</td>
            <td>{data["semantic_status"]}</td>
            <td>{data["elastic_status"]}</td>
        </tr>
        """

    return table_rows

def build_summary(rows):
    lexical_rows = [
        row for row in rows
        if row["type"] == "lexical" and row["status"] == 200
    ]

    semantic_rows = [
        row for row in rows
        if row["type"] == "semantic_vector" and row["status"] == 200
    ]

    elastic_rows = [
        row for row in rows
        if row["type"] == "elasticsearch_bm25" and row["status"] == 200
    ]

    lexical_cold_rows = [
        row for row in rows
        if row["type"] == "lexical_cold" and row["status"] == 200
    ]

    semantic_cold_rows = [
        row for row in rows
        if row["type"] == "semantic_vector_cold" and row["status"] == 200
    ]

    elastic_cold_rows = [
        row for row in rows
        if row["type"] == "elasticsearch_bm25_cold" and row["status"] == 200
    ]

    if not lexical_rows or not semantic_rows or not elastic_rows:
        return """
        <div class="summary">
            <div class="metric-card warning">No complete benchmark data available.</div>
        </div>
        """

    lexical_avg = statistics.mean(row["response_time_ms"] for row in lexical_rows)
    semantic_avg = statistics.mean(row["response_time_ms"] for row in semantic_rows)
    elastic_avg = statistics.mean(row["response_time_ms"] for row in elastic_rows)

    lexical_cold_avg = (
        statistics.mean(row["response_time_ms"] for row in lexical_cold_rows)
        if lexical_cold_rows else 0.0
    )

    semantic_cold_avg = (
        statistics.mean(row["response_time_ms"] for row in semantic_cold_rows)
        if semantic_cold_rows else 0.0
    )

    elastic_cold_avg = (
        statistics.mean(row["response_time_ms"] for row in elastic_cold_rows)
        if elastic_cold_rows else 0.0
    )

    averages = {
        "Lexical": lexical_avg,
        "Semantic Vector": semantic_avg,
        "Elasticsearch BM25": elastic_avg,
    }

    fastest = min(averages, key=averages.get)

    return f"""
    <div class="summary">
        <div class="metric-card">
            <span>Lexical avg</span>
            <strong>{lexical_avg:.2f} ms</strong>
        </div>
        <div class="metric-card">
            <span>Semantic vector avg</span>
            <strong>{semantic_avg:.2f} ms</strong>
        </div>
        <div class="metric-card">
            <span>Elasticsearch BM25 avg</span>
            <strong>{elastic_avg:.2f} ms</strong>
        </div>
        <div class="metric-card">
            <span>Lexical cold start</span>
            <strong>{lexical_cold_avg:.2f} ms</strong>
        </div>
        <div class="metric-card">
            <span>Semantic cold start</span>
            <strong>{semantic_cold_avg:.2f} ms</strong>
        </div>
        <div class="metric-card">
            <span>Elasticsearch cold start</span>
            <strong>{elastic_cold_avg:.2f} ms</strong>
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
        "lexical": [],
        "semantic_vector": [],
        "elasticsearch_bm25": [],
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
                    Elasticsearch endpoint: {settings.search_url}/elastic/search
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
                            <th>Lexical avg time</th>
                            <th>Semantic avg time</th>
                            <th>Elasticsearch BM25 avg time</th>
                            <th>Lexical results</th>
                            <th>Semantic results</th>
                            <th>Elasticsearch BM25 results</th>
                            <th>Fastest</th>
                            <th>Lexical status</th>
                            <th>Semantic status</th>
                            <th>Elasticsearch BM25 status</th>
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
                                label: 'Lexical',
                                data: benchmarkChartData.lexical
                            }},
                            {{
                                label: 'Semantic Vector',
                                data: benchmarkChartData.semantic_vector
                            }},
                            {{
                                label: 'Elasticsearch BM25',
                                data: benchmarkChartData.elasticsearch_bm25
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