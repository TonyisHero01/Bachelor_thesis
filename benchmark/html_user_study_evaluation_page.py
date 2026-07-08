import json

from html_common import h


def method_label(value):
    if value == "tfidf":
        return "TF-IDF"

    if value == "semantic_vector":
        return "Semantic Vector"

    if value == "elasticsearch_bm25":
        return "Elasticsearch BM25"

    if value == "no_clear_difference":
        return "No clear difference"

    if value == "unknown":
        return "Unknown"

    return value or "-"


def build_user_study_preference_chart_data(metrics):
    preferences = metrics.get("preferences", {}) if metrics else {}

    preferred = preferences.get("preferred_algorithm", {})
    easiest = preferences.get("easiest_to_understand", {})

    return {
        "preferredLabels": [
            method_label(key)
            for key in preferred.keys()
        ],
        "preferredCounts": list(preferred.values()),
        "easiestLabels": [
            method_label(key)
            for key in easiest.keys()
        ],
        "easiestCounts": list(easiest.values()),
    }


def build_user_study_summary(metrics):
    summary = metrics.get("summary", {}) if metrics else {}

    return f"""
        <div class="summary">
            <div class="metric-card">
                <span>Total user study responses</span>
                <strong>{summary.get("total_responses", 0)}</strong>
            </div>
        </div>
    """

def build_user_study_rating_table(metrics):
    ratings = metrics.get("ratings", {}) if metrics else {}

    if not ratings:
        return """
            <div class="empty">
                No user study rating data available.
            </div>
        """

    rows = ""

    for method, data in ratings.items():
        rows += f"""
            <tr>
                <td>{h(data.get("label", method))}</td>
                <td>{data.get("relevance", 0)}</td>
                <td>{data.get("ranking_quality", 0)}</td>
                <td>{data.get("result_diversity", 0)}</td>
                <td>{data.get("overall_satisfaction", 0)}</td>
            </tr>
        """

    return f"""
        <h3>Average User Ratings</h3>

        <table>
            <thead>
                <tr>
                    <th>Method</th>
                    <th>Relevance</th>
                    <th>Ranking quality</th>
                    <th>Result diversity</th>
                    <th>Overall satisfaction</th>
                </tr>
            </thead>

            <tbody>
                {rows}
            </tbody>
        </table>
    """


def build_user_study_recent_rows(metrics):
    recent_responses = metrics.get("recent_responses", []) if metrics else []

    if not recent_responses:
        return """
            <tr>
                <td colspan="9">No user study responses found.</td>
            </tr>
        """

    rows = ""

    for item in recent_responses:
        rows += f"""
            <tr>
                <td>{h(item.get("created_at", ""))}</td>
                <td>{h(item.get("search_task", ""))}</td>
                <td>{h(item.get("tested_query", ""))}</td>
                <td>{h(method_label(item.get("preferred_algorithm", "")))}</td>
                <td>{h(method_label(item.get("easiest_to_understand", "")))}</td>
                <td>{h(item.get("comment", ""))}</td>
                <td>{h(item.get("session_id", ""))}</td>
                <td>{h(item.get("customer_id", ""))}</td>
                <td>{h(item.get("id", ""))}</td>
            </tr>
        """

    return rows


def build_user_study_section(metrics):
    chart_data = build_user_study_preference_chart_data(metrics)

    return f"""
        <h2>User Study Analysis</h2>

        <div class="notice" style="margin-bottom:20px;">
            This section summarizes subjective user feedback collected from the
            search algorithm comparison user study form. It complements the
            ESCI-based automatic relevance evaluation with human ratings.
        </div>

        {build_user_study_summary(metrics)}

        {build_user_study_rating_table(metrics)}

        <div class="chart-card">
            <h3>Preferred Search Algorithm</h3>
            <canvas id="userStudyPreferredChart"></canvas>
        </div>

        <div class="chart-card">
            <h3>Easiest Algorithm to Understand</h3>
            <canvas id="userStudyEasiestChart"></canvas>
        </div>

        <h3>Recent User Study Responses</h3>

        <div class="metric-table-wrapper">
            <table class="wide-table">
                <thead>
                    <tr>
                        <th>Created at</th>
                        <th>Search task</th>
                        <th>Tested query</th>
                        <th>Preferred algorithm</th>
                        <th>Easiest to understand</th>
                        <th>Comment</th>
                        <th>Session</th>
                        <th>Customer</th>
                        <th>ID</th>
                    </tr>
                </thead>

                <tbody>
                    {build_user_study_recent_rows(metrics)}
                </tbody>
            </table>
        </div>

        <script>
            const userStudyChartData = {json.dumps(chart_data)};

            function createUserStudyBarChart(canvasId, labels, values, title) {{
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
                                beginAtZero: true,
                                ticks: {{
                                    precision: 0
                                }}
                            }}
                        }}
                    }}
                }});
            }}

            createUserStudyBarChart(
                'userStudyPreferredChart',
                userStudyChartData.preferredLabels,
                userStudyChartData.preferredCounts,
                'Responses'
            );

            createUserStudyBarChart(
                'userStudyEasiestChart',
                userStudyChartData.easiestLabels,
                userStudyChartData.easiestCounts,
                'Responses'
            );
        </script>
    """