import json

from html_common import h, percentage, status_label, format_score


def build_recommendation_log_chart_data(recommendation_log):
    if not recommendation_log:
        return {
            "eventLabels": [],
            "eventCounts": [],
            "algorithmLabels": [],
            "algorithmCounts": [],
            "pageLabels": [],
            "pageCounts": [],
        }

    return {
        "eventLabels": [
            item.get("label", "unknown")
            for item in recommendation_log.get("by_event_type", [])
        ],
        "eventCounts": [
            item.get("count", 0)
            for item in recommendation_log.get("by_event_type", [])
        ],
        "algorithmLabels": [
            item.get("label", "unknown")
            for item in recommendation_log.get("by_algorithm", [])
        ],
        "algorithmCounts": [
            item.get("count", 0)
            for item in recommendation_log.get("by_algorithm", [])
        ],
        "pageLabels": [
            item.get("label", "unknown")
            for item in recommendation_log.get("by_page_type", [])
        ],
        "pageCounts": [
            item.get("count", 0)
            for item in recommendation_log.get("by_page_type", [])
        ],
    }


def build_recommendation_log_filter_form(recommendation_log):
    filters = recommendation_log.get("filters", {}) if recommendation_log else {}

    def value(name):
        return h(filters.get(name, ""))

    return f"""
    <div class="chart-card">
        <h2>Recommendation Event Log</h2>

        <div class="notice">
            This section shows user-facing recommendation events recorded in
            <strong>recommendation_event_log</strong>.
            It can be used to inspect impressions, clicks, algorithms, pages,
            sessions and recommended products.
        </div>

        <form method="get" action="/evaluation">
            <div class="filter-grid">

                <div>
                    <label>Event type</label>
                    <select name="eventType">
                        <option value="">All</option>
                        <option value="impression" {"selected" if filters.get("event_type") == "impression" else ""}>Impression</option>
                        <option value="click" {"selected" if filters.get("event_type") == "click" else ""}>Click</option>
                    </select>
                </div>

                <div>
                    <label>Page type</label>
                    <input type="text" name="pageType" value="{value("page_type")}" placeholder="homepage, product_detail...">
                </div>

                <div>
                    <label>Algorithm</label>
                    <input type="text" name="algorithm" value="{value("algorithm")}" placeholder="global_homepage...">
                </div>

                <div>
                    <label>Session ID</label>
                    <input type="text" name="sessionId" value="{value("session_id")}" placeholder="session id">
                </div>

                <div>
                    <label>Customer ID</label>
                    <input type="text" name="customerId" value="{value("customer_id")}" placeholder="customer id">
                </div>

                <div>
                    <label>Source SKU</label>
                    <input type="text" name="sourceSku" value="{value("source_sku")}" placeholder="source sku">
                </div>

                <div>
                    <label>Recommended SKU</label>
                    <input type="text" name="recommendedSku" value="{value("recommended_sku")}" placeholder="recommended sku">
                </div>

                <div>
                    <label>Date from</label>
                    <input type="datetime-local" name="dateFrom" value="{value("date_from")}">
                </div>

                <div>
                    <label>Date to</label>
                    <input type="datetime-local" name="dateTo" value="{value("date_to")}">
                </div>

                <div>
                    <label>Limit</label>
                    <input type="number" name="limit" min="20" max="1000" value="{value("limit")}">
                </div>
            </div>

            <div style="margin-top:18px;">
                <button type="submit">Apply filters</button>

                <button
                    type="submit"
                    formaction="/evaluation/recommendation-log/csv"
                    formmethod="get">
                    Download CSV
                </button>

                <a href="/evaluation" class="secondary">Clear filters</a>
            </div>
        </form>
    </div>
    """


def build_recommendation_log_summary(recommendation_log):
    summary = recommendation_log.get("summary", {}) if recommendation_log else {}

    total_events = int(summary.get("total_events", 0))
    impression_count = int(summary.get("impression_count", 0))
    click_count = int(summary.get("click_count", 0))
    ctr = float(summary.get("ctr", 0))
    unique_sessions = int(summary.get("unique_sessions", 0))
    unique_customers = int(summary.get("unique_customers", 0))
    unique_recommended_products = int(summary.get("unique_recommended_products", 0))

    return f"""
    <div class="summary">
        <div class="metric-card">
            <span>Total events</span>
            <strong>{total_events}</strong>
        </div>

        <div class="metric-card">
            <span>Impressions</span>
            <strong>{impression_count}</strong>
        </div>

        <div class="metric-card">
            <span>Clicks</span>
            <strong>{click_count}</strong>
        </div>

        <div class="metric-card {status_label(ctr, 0.05)}">
            <span>CTR</span>
            <strong>{percentage(ctr)}</strong>
        </div>

        <div class="metric-card">
            <span>Unique sessions</span>
            <strong>{unique_sessions}</strong>
        </div>

        <div class="metric-card">
            <span>Known customers</span>
            <strong>{unique_customers}</strong>
        </div>

        <div class="metric-card">
            <span>Recommended products</span>
            <strong>{unique_recommended_products}</strong>
        </div>
    </div>
    """


def build_recommendation_log_rows(recommendation_log):
    events = recommendation_log.get("events", []) if recommendation_log else []

    if not events:
        return """
        <tr>
            <td colspan="11">No recommendation events found for current filters.</td>
        </tr>
        """

    rows = ""

    for item in events:
        event_type = item.get("event_type", "")
        event_class = "click" if event_type == "click" else "impression"

        source_display = h(item.get("source_sku", ""))
        source_name = h(item.get("source_name", ""))

        if source_name:
            source_display += f"""
            <div class="small-muted">{source_name}</div>
            """

        recommended_display = h(item.get("recommended_sku", ""))
        recommended_name = h(item.get("recommended_name", ""))

        if recommended_name:
            recommended_display += f"""
            <div class="small-muted">{recommended_name}</div>
            """

        rows += f"""
        <tr>
            <td>{h(item.get("created_at", ""))}</td>
            <td><span class="tag {event_class}">{h(event_type)}</span></td>
            <td>{h(item.get("page_type", ""))}</td>
            <td>{h(item.get("algorithm", ""))}</td>
            <td>{h(item.get("rank_position", ""))}</td>
            <td>{format_score(item.get("score"))}</td>
            <td>{source_display or "-"}</td>
            <td>{recommended_display}</td>
            <td>{h(item.get("session_id", ""))}</td>
            <td>{h(item.get("customer_id", ""))}</td>
            <td>{h(item.get("id", ""))}</td>
        </tr>
        """

    return rows


def build_recommendation_log_section(recommendation_log):
    chart_data = build_recommendation_log_chart_data(recommendation_log)

    return f"""
    {build_recommendation_log_filter_form(recommendation_log)}

    {build_recommendation_log_summary(recommendation_log)}

    <div class="chart-card">
        <h2>Recommendation Events by Type</h2>
        <canvas id="recommendationEventTypeChart"></canvas>
    </div>

    <div class="chart-card">
        <h2>Recommendation Events by Algorithm</h2>
        <canvas id="recommendationAlgorithmChart"></canvas>
    </div>

    <div class="chart-card">
        <h2>Recommendation Events by Page Type</h2>
        <canvas id="recommendationPageTypeChart"></canvas>
    </div>

    <h2>Recent Recommendation Events</h2>

    <div class="metric-table-wrapper">
        <table class="wide-table">
            <thead>
                <tr>
                    <th>Created at</th>
                    <th>Event</th>
                    <th>Page</th>
                    <th>Algorithm</th>
                    <th>Rank</th>
                    <th>Score</th>
                    <th>Source product</th>
                    <th>Recommended product</th>
                    <th>Session</th>
                    <th>Customer</th>
                    <th>ID</th>
                </tr>
            </thead>

            <tbody>
                {build_recommendation_log_rows(recommendation_log)}
            </tbody>
        </table>
    </div>

    <script>
        const recommendationChartData = {json.dumps(chart_data)};

        function createRecommendationBarChart(canvasId, labels, values, title) {{
            const canvas = document.getElementById(canvasId);

            if (!canvas || labels.length === 0) {{
                return;
            }}

            new Chart(canvas, {{
                type: 'bar',
                data: {{
                    labels: labels,
                    datasets: [
                        {{
                            label: title,
                            data: values
                        }}
                    ]
                }},
                options: {{
                    responsive: true,
                    scales: {{
                        y: {{
                            beginAtZero: true
                        }}
                    }}
                }}
            }});
        }}

        createRecommendationBarChart(
            'recommendationEventTypeChart',
            recommendationChartData.eventLabels,
            recommendationChartData.eventCounts,
            'Events'
        );

        createRecommendationBarChart(
            'recommendationAlgorithmChart',
            recommendationChartData.algorithmLabels,
            recommendationChartData.algorithmCounts,
            'Events'
        );

        createRecommendationBarChart(
            'recommendationPageTypeChart',
            recommendationChartData.pageLabels,
            recommendationChartData.pageCounts,
            'Events'
        );
    </script>
    """