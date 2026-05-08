import statistics
from datetime import datetime
import json
from config import settings


def percentage(value):
    return f"{value * 100:.0f}%"


def status_label(value, good_threshold=0.65):
    if value >= good_threshold:
        return "good"

    if value >= 0.5:
        return "warn"

    return "bad"

def build_benchmark_chart_data(rows):
    grouped = {}

    for row in rows:
        if row["type"] == "vector_cold":
            continue

        query = row["query"]

        if query not in grouped:
            grouped[query] = {
                "vector": 0,
                "sql_like": 0,
            }

        if row["type"] == "vector":
            grouped[query]["vector"] = round(row["response_time_ms"], 2)

        if row["type"] == "sql_like":
            grouped[query]["sql_like"] = round(row["response_time_ms"], 2)

    return {
        "labels": list(grouped.keys()),
        "vector": [item["vector"] for item in grouped.values()],
        "sql_like": [item["sql_like"] for item in grouped.values()],
    }

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
    vector_rows = [
        row for row in rows
        if row["type"] == "vector" and row["status"] == 200
    ]

    sql_rows = [
        row for row in rows
        if row["type"] == "sql_like" and row["status"] == 200
    ]

    cold_rows = [
        row for row in rows
        if row["type"] == "vector_cold" and row["status"] == 200
    ]

    if not vector_rows or not sql_rows:
        return """
        <div class="summary">
            <div class="metric-card warning">No complete benchmark data available.</div>
        </div>
        """

    vector_avg = statistics.mean(
        row["response_time_ms"] for row in vector_rows
    )

    sql_avg = statistics.mean(
        row["response_time_ms"] for row in sql_rows
    )

    cold_avg = (
        statistics.mean(row["response_time_ms"] for row in cold_rows)
        if cold_rows else 0.0
    )

    if vector_avg < sql_avg:
        result_text = f"Vector search is {(sql_avg / vector_avg):.2f}× faster on average."
    else:
        result_text = f"SQL LIKE is {(vector_avg / sql_avg):.2f}× faster on average."

    return f"""
    <div class="summary">
        <div class="metric-card">
            <span>Vector avg</span>
            <strong>{vector_avg:.2f} ms</strong>
        </div>
        <div class="metric-card">
            <span>SQL LIKE avg</span>
            <strong>{sql_avg:.2f} ms</strong>
        </div>
        <div class="metric-card">
            <span>Vector cold start</span>
            <strong>{cold_avg:.2f} ms</strong>
        </div>
        <div class="metric-card result">
            <span>Result</span>
            <strong>{result_text}</strong>
        </div>
    </div>
    """


def page_style():
    return """
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            background: #f4f6f8;
            color: #222;
        }

        .page {
            max-width: 1250px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .card {
            background: white;
            padding: 28px;
            border-radius: 18px;
            box-shadow: 0 10px 30px rgba(0,0,0,.08);
        }

        h1 {
            margin-top: 0;
            font-size: 30px;
        }

        h2 {
            margin-top: 32px;
        }

        .meta {
            color: #666;
            line-height: 1.6;
            margin-bottom: 18px;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 24px 0;
        }

        a,
        button {
            display: inline-block;
            padding: 10px 16px;
            border-radius: 10px;
            border: none;
            background: #111827;
            color: white;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
        }

        a.secondary {
            background: #4b5563;
        }

        button {
            background: #2563eb;
        }

        .summary,
        .metrics {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 16px;
            margin: 24px 0;
        }

        .metric-card {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 18px;
            line-height: 1.5;
        }

        .metric-card span {
            display: block;
            color: #6b7280;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .metric-card strong {
            display: block;
            font-size: 22px;
        }

        .metric-card.good {
            background: #ecfdf5;
            border-color: #a7f3d0;
        }

        .metric-card.warn {
            background: #fffbeb;
            border-color: #fde68a;
        }

        .metric-card.bad {
            background: #fef2f2;
            border-color: #fecaca;
        }

        .metric-card.result strong {
            font-size: 16px;
        }

        .notice {
            padding: 16px;
            border-radius: 14px;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1e3a8a;
            line-height: 1.6;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 22px;
            overflow: hidden;
        }

        th,
        td {
            border-bottom: 1px solid #e5e7eb;
            padding: 11px;
            text-align: left;
            font-size: 14px;
        }

        th {
            background: #f3f4f6;
            color: #374151;
        }

        tr:hover td {
            background: #f9fafb;
        }

        .empty {
            color: #6b7280;
            background: #f9fafb;
            padding: 18px;
            border-radius: 14px;
        }

        .chart-card {
            margin: 28px 0;
            padding: 20px;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
        }

        .chart-card h2 {
            margin-top: 0;
        }
    </style>
    """


def render_benchmark_page(rows):
    if not rows:
        summary_html = ""
        table_rows = """
        <tr>
            <td colspan="9">
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
        "vector": [],
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
                                label: 'Vector search',
                                data: benchmarkChartData.vector
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


def render_evaluation_page(report):
    recommendation_chart = {
        "labels": [],
        "values": [],
    }

    search_chart = {
        "labels": [],
        "times": [],
    }

    if "error" in report:
        body = """
        <div class="empty">
            No evaluation report has been generated yet.
        </div>
        """
    else:
        search = report.get("search_evaluation", {})
        rec = report.get("recommendation_evaluation", {})

        hit_rate = float(search.get("result_hit_rate", 0))
        avg_time = float(search.get("avg_response_time_ms", 0))
        interest_similarity = float(
            rec.get("avg_interest_similarity", 0)
        )

        category_similarity = float(
            rec.get("avg_category_similarity", 0)
        )

        material_similarity = float(
            rec.get("avg_material_similarity", 0)
        )

        color_similarity = float(
            rec.get("avg_color_similarity", 0)
        )

        size_similarity = float(
            rec.get("avg_size_similarity", 0)
        )

        diversity = float(
            rec.get("avg_category_diversity", 0)
        )

        recommendation_chart = {
            "labels": [
                "Interest",
                "Category",
                "Material",
                "Color",
                "Size",
            ],
            "values": [
                round(interest_similarity * 100, 2),
                round(category_similarity * 100, 2),
                round(material_similarity * 100, 2),
                round(color_similarity * 100, 2),
                round(size_similarity * 100, 2),
            ],
        }

        search_chart = {
            "labels": [
                item.get("query", "-")
                for item in search.get("details", [])
            ],
            "times": [
                round(float(item.get("response_time_ms", 0)), 2)
                for item in search.get("details", [])
            ],
        }

        diversity_class = "good" if diversity >= 2 else "warn"

        if avg_time <= 50:
            speed_label = "Fast"
            speed_class = "good"
        elif avg_time <= 200:
            speed_label = "Acceptable"
            speed_class = "warn"
        else:
            speed_label = "Slow"
            speed_class = "bad"

        body = f"""
            <div class="metrics">

                <div class="metric-card {status_label(hit_rate)}">
                    <span>Search result hit rate</span>
                    <strong>{percentage(hit_rate)}</strong>
                </div>

                <div class="metric-card {speed_class}">
                    <span>Average search response</span>
                    <strong>{avg_time:.2f} ms</strong>
                    <span>{speed_label}</span>
                </div>

                <div class="metric-card">
                    <span>Evaluated users</span>
                    <strong>{rec.get("evaluated_users", 0)}</strong>
                </div>

                <div class="metric-card {status_label(interest_similarity)}">
                    <span>Recommendation interest similarity</span>
                    <strong>{percentage(interest_similarity)}</strong>
                </div>

                <div class="metric-card {status_label(category_similarity)}">
                    <span>Category similarity</span>
                    <strong>{percentage(category_similarity)}</strong>
                </div>

                <div class="metric-card">
                    <span>Material similarity</span>
                    <strong>{percentage(material_similarity)}</strong>
                </div>

                <div class="metric-card">
                    <span>Color similarity</span>
                    <strong>{percentage(color_similarity)}</strong>
                </div>

                <div class="metric-card">
                    <span>Size similarity</span>
                    <strong>{percentage(size_similarity)}</strong>
                </div>

                <div class="metric-card {diversity_class}">
                    <span>Average category diversity</span>
                    <strong>{diversity:.2f}</strong>
                </div>

            </div>

            <div class="notice">
                Recommendation evaluation compares recommended products
                against the customer's historical interest profile.

                The system measures similarity across categories,
                materials, colors, and sizes.

                Higher similarity means recommendations are more aligned
                with previous customer behavior.

                Diversity measures how many different categories appear
                in recommendation results.
            </div>

            <div class="chart-card">
                <h2>Recommendation Similarity</h2>
                <canvas id="recommendationChart"></canvas>
            </div>

            <div class="chart-card">
                <h2>Search Response Time by Query</h2>
                <canvas id="searchTimeChart"></canvas>
            </div>

            <h2>Search Details</h2>

            <table>
                <thead>
                    <tr>
                        <th>Query</th>
                        <th>Status</th>
                        <th>Response time</th>
                        <th>Results</th>
                        <th>Has results</th>
                    </tr>
                </thead>

                <tbody>
                    {build_search_detail_rows(search.get("details", []))}
                </tbody>
            </table>
            """

    return f"""
    <!doctype html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>Search Evaluation Report</title>
        {page_style()}
    </head>
    <body>
        <div class="page">
            <div class="card">
                <h1>Search & Recommendation Evaluation</h1>

                <div class="actions">
                    <a href="/">Back to benchmark</a>

                    <form method="post" action="/evaluation/generate" style="display:inline;">
                        <button type="submit">Generate evaluation report</button>
                    </form>
                </div>

                {body}
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            const recommendationChartData = {json.dumps(recommendation_chart if "error" not in report else {"labels": [], "values": []})};
            const searchChartData = {json.dumps(search_chart if "error" not in report else {"labels": [], "times": []})};

            if (recommendationChartData.labels.length > 0) {{
                new Chart(document.getElementById('recommendationChart'), {{
                    type: 'radar',
                    data: {{
                        labels: recommendationChartData.labels,
                        datasets: [{{
                            label: 'Similarity %',
                            data: recommendationChartData.values
                        }}]
                    }},
                    options: {{
                        responsive: true,
                        scales: {{
                            r: {{
                                beginAtZero: true,
                                max: 100
                            }}
                        }}
                    }}
                }});
            }}

            if (searchChartData.labels.length > 0) {{
                new Chart(document.getElementById('searchTimeChart'), {{
                    type: 'bar',
                    data: {{
                        labels: searchChartData.labels,
                        datasets: [{{
                            label: 'Response time ms',
                            data: searchChartData.times
                        }}]
                    }},
                    options: {{
                        responsive: true,
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


def build_search_detail_rows(details):
    if not details:
        return """
        <tr>
            <td colspan="5">No search details available.</td>
        </tr>
        """

    rows = ""

    for item in details:
        rows += f"""
        <tr>
            <td>{item.get("query", "-")}</td>
            <td>{item.get("status", "-")}</td>
            <td>{float(item.get("response_time_ms", 0)):.2f} ms</td>
            <td>{item.get("result_count", 0)}</td>
            <td>{"Yes" if item.get("has_results") else "No"}</td>
        </tr>
        """

    return rows