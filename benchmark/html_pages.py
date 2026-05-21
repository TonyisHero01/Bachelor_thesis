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

        if valid_times:
            fastest = min(valid_times, key=valid_times.get)
        else:
            fastest = "-"

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

        .metric-table-wrapper {

            width: 100%;

            overflow-x: auto;

            margin: 24px 0;

        }

        .metric-table {

            width: 100%;

            min-width: 980px;

            border-collapse: separate;

            border-spacing: 12px;

        }

        .metric-table th,

        .metric-table td {

            border: none;

            padding: 0;

            background: transparent;

            vertical-align: top;

        }

        .metric-table thead th {
            color: #4b5563;
            font-size: 14px;
            text-align: center;
            white-space: nowrap;
        }

        .metric-table tbody th {
            font-size: 15px;
            text-align: left;
            white-space: nowrap;
            padding-top: 24px;
        }

        .metric-table .metric-card {
            margin: 0;
            min-width: 60px;

        }
    </style>
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

def build_search_method_chart_data(details):
    grouped = {}

    for item in details:
        query = item.get("query", "-")
        method = item.get("method", "-")

        if query not in grouped:
            grouped[query] = {
                "tfidf": 0,
                "semantic_vector": 0,
                "elasticsearch_bm25": 0,
            }

        if method in grouped[query]:
            grouped[query][method] = round(
                float(item.get("response_time_ms", 0)),
                2
            )

    return {
        "labels": list(grouped.keys()),
        "tfidf": [item["tfidf"] for item in grouped.values()],
        "semantic_vector": [item["semantic_vector"] for item in grouped.values()],
        "elasticsearch_bm25": [item["elasticsearch_bm25"] for item in grouped.values()],
    }

def method_label(method):
    if method == "tfidf":
        return "TF-IDF"
    if method == "semantic_vector":
        return "Semantic Vector"
    if method == "elasticsearch_bm25":
        return "Elasticsearch BM25"
    return method


def build_search_metric_matrix(search_summary, query_count):
    methods = [
        "tfidf",
        "semantic_vector",
        "elasticsearch_bm25",
    ]

    metrics = [
        ("Hit rate", "result_hit_rate", "percent"),
        ("Precision@10", "avg_precision_at_k", "percent"),
        ("Recall@10", "avg_recall_at_k", "percent"),
        ("NDCG@10", "avg_ndcg_at_k", "percent"),
        ("MRR", "avg_mrr", "percent"),
        ("Avg response", "avg_response_time_ms", "ms"),
    ]

    rows = ""

    for method in methods:
        summary = search_summary.get(method, {})

        rows += f"""
        <tr>
            <th>{method_label(method)}</th>
        """

        for title, key, value_type in metrics:
            value = float(summary.get(key, 0))

            if value_type == "percent":
                display_value = percentage(value)
                css_class = status_label(value)
            else:
                display_value = f"{value:.2f} ms"
                css_class = ""

            rows += f"""
            <td>
                <div class="metric-card {css_class}">
                    <span>{title}</span>
                    <strong>{display_value}</strong>
                </div>
            </td>
            """

        rows += "</tr>"

    return f"""
    <div class="metric-card" style="margin-bottom:20px;">
        <span>Evaluated ESCI queries</span>
        <strong>{query_count}</strong>
    </div>

    <div class="metric-table-wrapper">
        <table class="metric-table">
            <thead>
                <tr>
                    <th>Method</th>
                    {''.join(f'<th>{title}</th>' for title, _, _ in metrics)}
                </tr>
            </thead>
            <tbody>
                {rows}
            </tbody>
        </table>
    </div>
    """

def render_evaluation_page(report, config=None):

    if config is None:
        config = {}

    search_chart = {
        "labels": [],
        "tfidf": [],
        "semantic_vector": [],
        "elasticsearch_bm25": [],
    }

    if "error" in report:
        body = """
        <div class="empty">
            No evaluation report has been generated yet.
        </div>
        """
    else:
        search = report.get("search_evaluation", {})

        search_summary = search.get("summary", {})

        query_count = int(search.get("query_count", 0))

        search_chart = build_search_method_chart_data(
            search.get("details", [])
        )

        body = f"""
            {build_search_metric_matrix(search_summary, query_count)}

            <div class="notice">
                This evaluation uses Amazon ESCI ground-truth labels.

                Exact and Substitute labels are treated as relevant results.

                Precision@10 measures how many returned top results are relevant.

                Recall@10 measures how many known relevant products are retrieved.

                NDCG@10 measures whether highly relevant products appear near the top.

                MRR measures how early the first relevant result appears.
            </div>

            <div class="chart-card">
                <h2>Search Response Time by Query</h2>
                <canvas id="searchTimeChart"></canvas>
            </div>

            <h2>Search Details</h2>

            <table>
                <thead>
                    <tr>
                        <th>Method</th>
                        <th>Query</th>
                        <th>Status</th>
                        <th>Response time</th>
                        <th>Results</th>
                        <th>Precision@10</th>
                        <th>Recall@10</th>
                        <th>NDCG@10</th>
                        <th>MRR</th>
                        <th>Has results</th>
                    </tr>
                </thead>

                <tbody>
                    {build_search_detail_rows(search.get("details", []))}
                </tbody>
            </table>

            <h2>Top Search Results Preview</h2>

            {build_top_results_preview(search.get("details", []))}
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
                <h1>Search Reliability Evaluation</h1>

                <div class="actions">
                    <a href="/">Back to benchmark</a>

                    <form method="post" action="/evaluation/generate" style="display:inline;">
                        <button type="submit">Generate evaluation report</button>
                    </form>
                </div>
                <div class="notice" style="margin-bottom:20px;">
                    Search quality is evaluated using Amazon ESCI ground-truth labels.
                    Exact and Substitute labels are treated as relevant results.
                </div>

                <div class="chart-card">
                    <h2>Current Search Configuration</h2>

                    <form method="post" action="/evaluation/update-config">

                        <table>
                            <thead>
                                <tr>
                                    <th>Setting</th>
                                    <th>Value</th>
                                </tr>
                            </thead>

                            <tbody>

                                <tr>
                                    <td>Name weight</td>
                                    <td>
                                        <input type="number" step="1"
                                            name="nameWeight"
                                            value="{config.get('name_weight', 20)}">
                                    </td>
                                </tr>

                                <tr>
                                    <td>Description weight</td>
                                    <td>
                                        <input type="number" step="1"
                                            name="descriptionWeight"
                                            value="{config.get('description_weight', 5)}">
                                    </td>
                                </tr>

                                <tr>
                                    <td>Category weight</td>
                                    <td>
                                        <input type="number" step="1"
                                            name="categoryWeight"
                                            value="{config.get('category_weight', 4)}">
                                    </td>
                                </tr>

                                <tr>
                                    <td>Material weight</td>
                                    <td>
                                        <input type="number" step="1"
                                            name="materialWeight"
                                            value="{config.get('material_weight', 2)}">
                                    </td>
                                </tr>

                                <tr>
                                    <td>Color weight</td>
                                    <td>
                                        <input type="number" step="1"
                                            name="colorWeight"
                                            value="{config.get('color_weight', 2)}">
                                    </td>
                                </tr>

                                <tr>
                                    <td>Size weight</td>
                                    <td>
                                        <input type="number" step="1"
                                            name="sizeWeight"
                                            value="{config.get('size_weight', 2)}">
                                    </td>
                                </tr>

                                <tr>
                                    <td>Same category recommendation</td>
                                    <td>
                                        <input type="number" step="0.01"
                                            name="sameCategoryRecommendationWeight"
                                            value="{config.get('same_category_recommendation_weight', 0.35)}">
                                    </td>
                                </tr>

                                <tr>
                                    <td>Same color recommendation</td>
                                    <td>
                                        <input type="number" step="0.01"
                                            name="sameColorRecommendationWeight"
                                            value="{config.get('same_color_recommendation_weight', 0.10)}">
                                    </td>
                                </tr>

                                <tr>
                                    <td>Same size recommendation</td>
                                    <td>
                                        <input type="number" step="0.01"
                                            name="sameSizeRecommendationWeight"
                                            value="{config.get('same_size_recommendation_weight', 0.10)}">
                                    </td>
                                </tr>

                                <tr>
                                    <td>Attributes weight</td>
                                    <td>
                                        <input type="number" step="1"
                                            name="attributesWeight"
                                            value="{config.get('attributes_weight', config.get('attributesWeight', 2))}">
                                    </td>
                                </tr>

                                <tr>
                                    <td>TF-IDF recommendation weight</td>
                                    <td>
                                        <input type="number" step="0.01"
                                            name="tfidfRecommendationWeight"
                                            value="{config.get('tfidf_recommendation_weight', config.get('tfidfRecommendationWeight', 1.0))}">
                                    </td>
                                </tr>

                                <tr>
                                    <td>Wishlist recommendation weight</td>
                                    <td>
                                        <input type="number" step="0.01"
                                            name="wishlistRecommendationWeight"
                                            value="{config.get('wishlist_recommendation_weight', config.get('wishlistRecommendationWeight', 0.30))}">
                                    </td>
                                </tr>

                                <tr>
                                    <td>Order history recommendation weight</td>
                                    <td>
                                        <input type="number" step="0.01"
                                            name="orderHistoryRecommendationWeight"
                                            value="{config.get('order_history_recommendation_weight', config.get('orderHistoryRecommendationWeight', 0.25))}">
                                    </td>
                                </tr>

                                <tr>
                                    <td>Search history recommendation weight</td>
                                    <td>
                                        <input type="number" step="0.01"
                                            name="searchHistoryRecommendationWeight"
                                            value="{config.get('search_history_recommendation_weight', config.get('searchHistoryRecommendationWeight', 0.20))}">
                                    </td>
                                </tr>

                                <tr>
                                    <td>View history recommendation weight</td>
                                    <td>
                                        <input type="number" step="0.01"
                                            name="viewHistoryRecommendationWeight"
                                            value="{config.get('view_history_recommendation_weight', config.get('viewHistoryRecommendationWeight', 0.35))}">
                                    </td>
                                </tr>

                                <tr>
                                    <td>Max recommendation per category</td>
                                    <td>
                                        <input type="number" step="1"
                                            name="maxRecommendationPerCategory"
                                            value="{config.get('max_recommendation_per_category', config.get('maxRecommendationPerCategory', 4))}">
                                    </td>
                                </tr>

                                <tr>
                                    <td>Recommendation diversity penalty</td>
                                    <td>
                                        <input type="number" step="0.01"
                                            name="recommendationDiversityPenalty"
                                            value="{config.get('recommendation_diversity_penalty', config.get('recommendationDiversityPenalty', 0.10))}">
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <div style="margin-top:20px;">
                            <button type="submit">
                                Save configuration to BMS
                            </button>
                        </div>

                    </form>
                </div>
                {body}
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            
            const searchChartData = {json.dumps(search_chart if "error" not in report else {"labels": [], "tfidf": [], "semantic_vector": [], "elasticsearch_bm25": []})};

            if (searchChartData.labels.length > 0) {{
                new Chart(document.getElementById('searchTimeChart'), {{
                    type: 'bar',
                    data: {{
                        labels: searchChartData.labels,
                        datasets: [
                            {{
                                label: 'TF-IDF response time ms',
                                data: searchChartData.tfidf
                            }},
                            {{
                                label: 'Semantic vector response time ms',
                                data: searchChartData.semantic_vector
                            }},
                            {{
                                label: 'Elasticsearch BM25 response time ms',
                                data: searchChartData.elasticsearch_bm25
                            }}
                        ]
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

def build_top_results_preview(details):
    if not details:
        return """
        <div class="empty">No top result preview available.</div>
        """

    html = ""

    for item in details[:20]:
        method = item.get("method", "-")

        if method == "semantic_vector":
            method_label = "Semantic Vector"
        elif method == "tfidf":
            method_label = "TF-IDF"
        elif method == "elasticsearch_bm25":
            method_label = "Elasticsearch BM25"
        else:
            method_label = method

        html += f"""
        <div class="chart-card">
            <h3>{method_label}: {item.get("query", "-")}</h3>
            <table>
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>SKU</th>
                        <th>Product</th>
                        <th>Category</th>
                        <th>ESCI label</th>
                    </tr>
                </thead>
                <tbody>
        """

        top_results = item.get("top_results", [])

        if not top_results:
            html += """
                <tr>
                    <td colspan="5">No results.</td>
                </tr>
            """
        else:
            for result in top_results:
                html += f"""
                    <tr>
                        <td>{result.get("rank", "-")}</td>
                        <td>{result.get("sku", "-")}</td>
                        <td>{result.get("name", "-")}</td>
                        <td>{result.get("category", "-")}</td>
                        <td>{result.get("label", "-")}</td>
                    </tr>
                """

        html += """
                </tbody>
            </table>
        </div>
        """

    return html

def build_search_detail_rows(details):
    if not details:
        return """
        <tr>
            <td colspan="10">No search details available.</td>
        </tr>
        """

    rows = ""

    for item in details:
        method = item.get("method", "-")

        if method == "semantic_vector":
            method_label = "Semantic Vector"
        elif method == "tfidf":
            method_label = "TF-IDF"
        elif method == "elasticsearch_bm25":
            method_label = "Elasticsearch BM25"
        else:
            method_label = method

        rows += f"""
        <tr>
            <td>{method_label}</td>
            <td>{item.get("query", "-")}</td>
            <td>{item.get("status", "-")}</td>
            <td>{float(item.get("response_time_ms", 0)):.2f} ms</td>
            <td>{item.get("result_count", 0)}</td>
            <td>{percentage(float(item.get("precision_at_k", 0)))}</td>
            <td>{percentage(float(item.get("recall_at_k", 0)))}</td>
            <td>{percentage(float(item.get("ndcg_at_k", 0)))}</td>
            <td>{percentage(float(item.get("mrr", 0)))}</td>
            <td>{"Yes" if item.get("has_results") else "No"}</td>
        </tr>
        """

    return rows