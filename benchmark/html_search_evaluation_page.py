import json

from html_common import percentage, status_label, page_style
from html_recommendation_log_page import build_recommendation_log_section


def method_label(method):
    if method == "tfidf":
        return "TF-IDF"

    if method == "semantic_vector":
        return "Semantic Vector"

    if method == "elasticsearch_bm25":
        return "Elasticsearch BM25"

    return method


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
                2,
            )

    return {
        "labels": list(grouped.keys()),
        "tfidf": [
            item["tfidf"]
            for item in grouped.values()
        ],
        "semantic_vector": [
            item["semantic_vector"]
            for item in grouped.values()
        ],
        "elasticsearch_bm25": [
            item["elasticsearch_bm25"]
            for item in grouped.values()
        ],
    }


def build_search_metric_matrix(search_summary, query_count):
    methods = [
        "tfidf",
        "semantic_vector",
        "elasticsearch_bm25",
    ]

    metrics = [
        ("Return rate", "result_return_rate", "percent"),
        ("HitRate@10", "avg_hit_rate_at_k", "percent"),
        ("Precision@10", "avg_precision_at_k", "percent"),
        ("Recall@10", "avg_recall_at_k", "percent"),
        ("F1@10", "avg_f1_at_k", "percent"),
        ("MAP", "avg_map", "percent"),
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


def build_search_detail_rows(details):
    if not details:
        return """
        <tr>
            <td colspan="13">No search details available.</td>
        </tr>
        """

    rows = ""

    for item in details:
        method = item.get("method", "-")

        rows += f"""
        <tr>
            <td>{method_label(method)}</td>
            <td>{item.get("query", "-")}</td>
            <td>{item.get("status", "-")}</td>
            <td>{float(item.get("response_time_ms", 0)):.2f} ms</td>
            <td>{item.get("result_count", 0)}</td>
            <td>{percentage(float(item.get("precision_at_k", 0)))}</td>
            <td>{percentage(float(item.get("recall_at_k", 0)))}</td>
            <td>{percentage(float(item.get("f1_at_k", 0)))}</td>
            <td>{percentage(float(item.get("ap", 0)))}</td>
            <td>{percentage(float(item.get("hit_rate_at_k", 0)))}</td>
            <td>{percentage(float(item.get("ndcg_at_k", 0)))}</td>
            <td>{percentage(float(item.get("mrr", 0)))}</td>
            <td>{"Yes" if item.get("has_results") else "No"}</td>
        </tr>
        """

    return rows


def build_top_results_preview(details):
    if not details:
        return """
        <div class="empty">No top result preview available.</div>
        """

    html = ""

    for item in details[:20]:
        method = item.get("method", "-")

        html += f"""
        <div class="chart-card">
            <h3>{method_label(method)}: {item.get("query", "-")}</h3>
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


def build_config_form(config):
    return f"""
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
    """


def build_search_evaluation_body(report):
    if "error" in report:
        return """
        <div class="empty">
            No evaluation report has been generated yet.
        </div>
        """, {
            "labels": [],
            "tfidf": [],
            "semantic_vector": [],
            "elasticsearch_bm25": [],
        }

    search = report.get("search_evaluation", {})
    search_summary = search.get("summary", {})
    query_count = int(search.get("query_count", 0))
    details = search.get("details", [])

    search_chart = build_search_method_chart_data(details)

    body = f"""
        {build_search_metric_matrix(search_summary, query_count)}

        <div class="notice">
            This evaluation uses Amazon ESCI ground-truth labels.

            Exact and Substitute labels are treated as relevant results.

            Precision@10 measures how many returned top results are relevant.

            Recall@10 measures how many known relevant products are retrieved.

            F1@10 balances precision and recall.

            MAP measures the average ranking quality across evaluated queries.

            HitRate@10 measures whether at least one relevant product appears in the top results.

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
                    <th>F1@10</th>
                    <th>MAP</th>
                    <th>HitRate@10</th>
                    <th>NDCG@10</th>
                    <th>MRR</th>
                    <th>Has results</th>
                </tr>
            </thead>

            <tbody>
                {build_search_detail_rows(details)}
            </tbody>
        </table>

        <h2>Top Search Results Preview</h2>

        {build_top_results_preview(details)}
    """

    return body, search_chart


def render_evaluation_page(report, config=None, recommendation_log=None):
    if config is None:
        config = {}

    body, search_chart = build_search_evaluation_body(report)

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

                {build_config_form(config)}

                {build_recommendation_log_section(recommendation_log)}

                {body}
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            const searchChartData = {json.dumps(search_chart)};

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