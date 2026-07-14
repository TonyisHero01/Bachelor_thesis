import json

from html_common import h, percentage, status_label, page_style
from html_recommendation_log_page import build_recommendation_log_section
from html_user_study_evaluation_page import build_user_study_section

def method_label(method):
    if method == "lexical":
        return "Lexical"

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
                "lexical": 0,
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
        "lexical": [
            item["lexical"]
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
        "lexical",
        "semantic_vector",
        "elasticsearch_bm25",
    ]

    metrics = [
        ("Return rate", "result_return_rate", "percent"),
        ("HitRate@10", "avg_hit_rate_at_k", "percent"),
        ("Precision@10", "avg_precision_at_k", "percent"),
        ("Recall@10", "avg_recall_at_k", "percent"),
        ("F1@10", "avg_f1_at_k", "percent"),
        ("MAP@10", "avg_map", "percent"),
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

SEARCH_METHODS = {
    "lexical": "Lexical",
    "semantic_vector": "Semantic Vector",
    "elasticsearch_bm25": "Elasticsearch BM25",
}


def first_value(data, *keys, default=None):
    if not isinstance(data, dict):
        return default

    for key in keys:
        if key in data and data[key] is not None:
            return data[key]

    return default


def nested_value(data, path, default=None):
    current = data

    for key in path:
        if isinstance(key, int):
            if not isinstance(current, list):
                return default

            if key < 0 or key >= len(current):
                return default

            current = current[key]
            continue

        if not isinstance(current, dict) or key not in current:
            return default

        current = current[key]

    return default if current is None else current


def normalize_method_configs(config):
    if not isinstance(config, dict):
        return {}, "lexical"

    possible_containers = [
        config.get("configs"),
        config.get("search_configs"),
        config.get("searchConfigs"),
    ]

    for container in possible_containers:
        if isinstance(container, dict):
            method_configs = {
                method: value
                for method, value in container.items()
                if method in SEARCH_METHODS
                and isinstance(value, dict)
            }

            if method_configs:
                active_method = first_value(
                    config,
                    "active_search_method",
                    "activeSearchMethod",
                    "search_method",
                    "searchMethod",
                    default="lexical",
                )

                if active_method not in SEARCH_METHODS:
                    active_method = "lexical"

                return method_configs, active_method

    direct_configs = {
        method: config[method]
        for method in SEARCH_METHODS
        if isinstance(config.get(method), dict)
    }

    if direct_configs:
        active_method = first_value(
            config,
            "active_search_method",
            "activeSearchMethod",
            default="lexical",
        )

        if active_method not in SEARCH_METHODS:
            active_method = next(iter(direct_configs))

        return direct_configs, active_method

    method = first_value(
        config,
        "search_method",
        "searchMethod",
        default="lexical",
    )

    if method not in SEARCH_METHODS:
        method = "lexical"

    return {
        method: config,
    }, method


def get_algorithm_settings(config):
    settings = first_value(
        config,
        "algorithmSettings",
        "algorithm_settings",
        default={},
    )

    return settings if isinstance(settings, dict) else {}


def input_row(
    label,
    name,
    value,
    input_type="number",
    step=None,
    minimum=None,
):
    attributes = [
        f'type="{h(input_type)}"',
        f'name="{h(name)}"',
        f'id="{h(name)}"',
        f'value="{h(value)}"',
    ]

    if step is not None:
        attributes.append(f'step="{h(step)}"')

    if minimum is not None:
        attributes.append(f'min="{h(minimum)}"')

    return f"""
    <tr>
        <td><label for="{h(name)}">{h(label)}</label></td>
        <td><input {' '.join(attributes)}></td>
    </tr>
    """


def checkbox_row(label, name, checked):
    checked_attribute = " checked" if bool(checked) else ""

    return f"""
    <tr>
        <td><label for="{h(name)}">{h(label)}</label></td>
        <td>
            <input
                type="checkbox"
                name="{h(name)}"
                id="{h(name)}"
                value="1"
                {checked_attribute}
            >
        </td>
    </tr>
    """


def select_row(label, name, value, options):
    option_html = ""

    for option_value, option_label in options:
        selected = (
            " selected"
            if str(value) == str(option_value)
            else ""
        )

        option_html += f"""
        <option value="{h(option_value)}"{selected}>
            {h(option_label)}
        </option>
        """

    return f"""
    <tr>
        <td><label for="{h(name)}">{h(label)}</label></td>
        <td>
            <select name="{h(name)}" id="{h(name)}">
                {option_html}
            </select>
        </td>
    </tr>
    """


def section_table(title, rows, method=None):
    method_attributes = ""

    if method:
        method_attributes = (
            ' class="search-method-settings"'
            f' data-search-method="{h(method)}"'
        )

    return f"""
    <div{method_attributes}>
        <h3>{h(title)}</h3>

        <table>
            <thead>
                <tr>
                    <th>Setting</th>
                    <th>Value</th>
                </tr>
            </thead>
            <tbody>
                {rows}
            </tbody>
        </table>
    </div>
    """

def build_config_form(config):
    method_configs, active_method = normalize_method_configs(
        config,
    )

    active_config = method_configs.get(
        active_method,
        {},
    )

    lexical_config = method_configs.get(
        "lexical",
        {},
    )
    lexical_settings = get_algorithm_settings(
        lexical_config,
    )

    semantic_config = method_configs.get(
        "semantic_vector",
        {},
    )
    semantic_settings = get_algorithm_settings(
        semantic_config,
    )

    elastic_config = method_configs.get(
        "elasticsearch_bm25",
        {},
    )
    elastic_settings = get_algorithm_settings(
        elastic_config,
    )

    common_rows = ""

    common_rows += input_row(
        "Configuration name",
        "name",
        first_value(
            active_config,
            "name",
            default="Search configuration",
        ),
        input_type="text",
    )

    method_options = ""

    for method, label in SEARCH_METHODS.items():
        selected = (
            " selected"
            if method == active_method
            else ""
        )

        method_options += f"""
        <option value="{h(method)}"{selected}>
            {h(label)}
        </option>
        """

    common_rows += f"""
    <tr>
        <td><label for="searchMethod">Search method</label></td>
        <td>
            <select name="searchMethod" id="searchMethod">
                {method_options}
            </select>
        </td>
    </tr>
    """

    common_number_fields = [
        (
            "Name weight",
            "nameWeight",
            ("nameWeight", "name_weight"),
            20,
            "1",
        ),
        (
            "Description weight",
            "descriptionWeight",
            ("descriptionWeight", "description_weight"),
            5,
            "1",
        ),
        (
            "Category weight",
            "categoryWeight",
            ("categoryWeight", "category_weight"),
            4,
            "1",
        ),
        (
            "Material weight",
            "materialWeight",
            ("materialWeight", "material_weight"),
            2,
            "1",
        ),
        (
            "Color weight",
            "colorWeight",
            ("colorWeight", "color_weight"),
            2,
            "1",
        ),
        (
            "Size weight",
            "sizeWeight",
            ("sizeWeight", "size_weight"),
            2,
            "1",
        ),
        (
            "Attributes weight",
            "attributesWeight",
            ("attributesWeight", "attributes_weight"),
            2,
            "1",
        ),
        (
            "Same category bonus",
            "sameCategoryBonus",
            ("sameCategoryBonus", "same_category_bonus"),
            0.35,
            "0.01",
        ),
        (
            "Same material bonus",
            "sameMaterialBonus",
            ("sameMaterialBonus", "same_material_bonus"),
            0.15,
            "0.01",
        ),
        (
            "Same color bonus",
            "sameColorBonus",
            ("sameColorBonus", "same_color_bonus"),
            0.10,
            "0.01",
        ),
        (
            "Same size bonus",
            "sameSizeBonus",
            ("sameSizeBonus", "same_size_bonus"),
            0.10,
            "0.01",
        ),
        (
            "Same category recommendation weight",
            "sameCategoryRecommendationWeight",
            (
                "sameCategoryRecommendationWeight",
                "same_category_recommendation_weight",
            ),
            0.35,
            "0.01",
        ),
        (
            "Same color recommendation weight",
            "sameColorRecommendationWeight",
            (
                "sameColorRecommendationWeight",
                "same_color_recommendation_weight",
            ),
            0.10,
            "0.01",
        ),
        (
            "Same size recommendation weight",
            "sameSizeRecommendationWeight",
            (
                "sameSizeRecommendationWeight",
                "same_size_recommendation_weight",
            ),
            0.10,
            "0.01",
        ),
        (
            "Wishlist recommendation weight",
            "wishlistRecommendationWeight",
            (
                "wishlistRecommendationWeight",
                "wishlist_recommendation_weight",
            ),
            0.30,
            "0.01",
        ),
        (
            "Order history recommendation weight",
            "orderHistoryRecommendationWeight",
            (
                "orderHistoryRecommendationWeight",
                "order_history_recommendation_weight",
            ),
            0.25,
            "0.01",
        ),
        (
            "Search history recommendation weight",
            "searchHistoryRecommendationWeight",
            (
                "searchHistoryRecommendationWeight",
                "search_history_recommendation_weight",
            ),
            0.20,
            "0.01",
        ),
        (
            "View history recommendation weight",
            "viewHistoryRecommendationWeight",
            (
                "viewHistoryRecommendationWeight",
                "view_history_recommendation_weight",
            ),
            0.35,
            "0.01",
        ),
        (
            "Max recommendation per category",
            "maxRecommendationPerCategory",
            (
                "maxRecommendationPerCategory",
                "max_recommendation_per_category",
            ),
            4,
            "1",
        ),
        (
            "Recommendation diversity penalty",
            "recommendationDiversityPenalty",
            (
                "recommendationDiversityPenalty",
                "recommendation_diversity_penalty",
            ),
            0.10,
            "0.01",
        ),
    ]

    for (
        label,
        name,
        keys,
        default,
        step,
    ) in common_number_fields:
        common_rows += input_row(
            label,
            name,
            first_value(
                active_config,
                *keys,
                default=default,
            ),
            step=step,
            minimum=0,
        )

    common_rows += checkbox_row(
        "Enable recommendations",
        "recommendationEnabled",
        first_value(
            active_config,
            "recommendationEnabled",
            "recommendation_enabled",
            default=True,
        ),
    )

    common_rows += checkbox_row(
        "Enable recommendation logging",
        "recommendationLoggingEnabled",
        first_value(
            active_config,
            "recommendationLoggingEnabled",
            "recommendation_logging_enabled",
            default=True,
        ),
    )

    lexical_rows = ""

    lexical_rows += checkbox_row(
        "Lowercase input",
        "lexical_lowercase",
        nested_value(
            lexical_settings,
            ("vectorizer", "lowercase"),
            True,
        ),
    )

    lexical_rows += input_row(
        "Minimum N-gram size",
        "lexical_ngram_min",
        nested_value(
            lexical_settings,
            ("vectorizer", "ngram_range", 0),
            1,
        ),
        step="1",
        minimum=1,
    )

    lexical_rows += input_row(
        "Maximum N-gram size",
        "lexical_ngram_max",
        nested_value(
            lexical_settings,
            ("vectorizer", "ngram_range", 1),
            2,
        ),
        step="1",
        minimum=1,
    )

    lexical_rows += input_row(
        "Hashing features",
        "lexical_n_features",
        nested_value(
            lexical_settings,
            ("vectorizer", "n_features"),
            262144,
        ),
        step="1",
        minimum=1,
    )

    lexical_rows += checkbox_row(
        "Alternate sign",
        "lexical_alternate_sign",
        nested_value(
            lexical_settings,
            ("vectorizer", "alternate_sign"),
            False,
        ),
    )

    lexical_rows += select_row(
        "Vector normalization",
        "lexical_normalization",
        nested_value(
            lexical_settings,
            ("vectorizer", "normalization"),
            "l2",
        ),
        [
            ("l2", "L2"),
            ("l1", "L1"),
            ("none", "None"),
        ],
    )

    lexical_rows += input_row(
        "Token pattern",
        "lexical_token_pattern",
        nested_value(
            lexical_settings,
            ("vectorizer", "token_pattern"),
            r"\b\w+\b",
        ),
        input_type="text",
    )

    lexical_rows += input_row(
        "Minimum query token matches",
        "lexical_candidate_minimum_query_token_matches",
        nested_value(
            lexical_settings,
            (
                "candidate_filter",
                "minimum_query_token_matches",
            ),
            1,
        ),
        step="1",
        minimum=0,
    )

    lexical_rows += checkbox_row(
        "Fallback to all documents",
        "lexical_fallback_to_all_documents",
        nested_value(
            lexical_settings,
            (
                "candidate_filter",
                "fallback_to_all_documents",
            ),
            True,
        ),
    )

    lexical_rows += checkbox_row(
        "Require all query tokens",
        "lexical_require_all_query_tokens",
        nested_value(
            lexical_settings,
            (
                "partial_match",
                "require_all_query_tokens",
            ),
            True,
        ),
    )

    lexical_rows += input_row(
        "Partial match minimum query token matches",
        "lexical_partial_minimum_query_token_matches",
        nested_value(
            lexical_settings,
            (
                "partial_match",
                "minimum_query_token_matches",
            ),
            1,
        ),
        step="1",
        minimum=0,
    )

    lexical_rows += input_row(
        "Partial match base score",
        "lexical_partial_base_score",
        nested_value(
            lexical_settings,
            ("partial_match", "base_score"),
            1.0,
        ),
        step="0.01",
        minimum=0,
    )

    lexical_rows += input_row(
        "Partial match merge bonus weight",
        "lexical_partial_merge_bonus_weight",
        nested_value(
            lexical_settings,
            (
                "partial_match",
                "merge_bonus_weight",
            ),
            0.20,
        ),
        step="0.01",
        minimum=0,
    )

    lexical_session_fields = [
        (
            "Current product weight",
            "lexical_session_current_product_weight",
            "current_product_weight",
            1.0,
            "0.01",
        ),
        (
            "Viewed product weight",
            "lexical_session_viewed_product_weight",
            "viewed_product_weight",
            0.70,
            "0.01",
        ),
        (
            "Cart product weight",
            "lexical_session_cart_product_weight",
            "cart_product_weight",
            0.90,
            "0.01",
        ),
        (
            "Maximum viewed seeds",
            "lexical_session_max_viewed_seeds",
            "max_viewed_seeds",
            5,
            "1",
        ),
        (
            "Maximum cart seeds",
            "lexical_session_max_cart_seeds",
            "max_cart_seeds",
            5,
            "1",
        ),
        (
            "Maximum total seeds",
            "lexical_session_max_total_seeds",
            "max_total_seeds",
            8,
            "1",
        ),
        (
            "Candidate multiplier",
            "lexical_session_candidate_multiplier",
            "candidate_multiplier",
            3,
            "1",
        ),
        (
            "Minimum candidates",
            "lexical_session_minimum_candidates",
            "minimum_candidates",
            10,
            "1",
        ),
    ]

    for label, name, key, default, step in lexical_session_fields:
        lexical_rows += input_row(
            label,
            name,
            nested_value(
                lexical_settings,
                ("session_recommendation", key),
                default,
            ),
            step=step,
            minimum=0,
        )

    semantic_rows = ""

    semantic_document_fields = [
        ("Include name", "semantic_document_name", "name", True),
        (
            "Include category",
            "semantic_document_category",
            "category",
            True,
        ),
        (
            "Include description",
            "semantic_document_description",
            "description",
            True,
        ),
        (
            "Include material",
            "semantic_document_material",
            "material",
            True,
        ),
        (
            "Include color",
            "semantic_document_color",
            "color",
            True,
        ),
        (
            "Include size",
            "semantic_document_size",
            "size",
            True,
        ),
        (
            "Include attributes",
            "semantic_document_attributes",
            "attributes",
            False,
        ),
    ]

    for label, name, key, default in semantic_document_fields:
        semantic_rows += checkbox_row(
            label,
            name,
            nested_value(
                semantic_settings,
                ("document_fields", key),
                default,
            ),
        )

    semantic_rows += input_row(
        "Embedding batch size",
        "semantic_embedding_batch_size",
        nested_value(
            semantic_settings,
            ("embedding", "batch_size"),
            32,
        ),
        step="1",
        minimum=1,
    )

    semantic_rows += checkbox_row(
        "Normalize embeddings",
        "semantic_normalize_embeddings",
        nested_value(
            semantic_settings,
            (
                "embedding",
                "normalize_embeddings",
            ),
            True,
        ),
    )

    semantic_rows += input_row(
        "Semantic similarity weight",
        "semantic_similarity_weight",
        nested_value(
            semantic_settings,
            (
                "reranking",
                "semantic_similarity_weight",
            ),
            0.75,
        ),
        step="0.01",
        minimum=0,
    )

    semantic_rows += input_row(
        "Lexical overlap weight",
        "semantic_lexical_overlap_weight",
        nested_value(
            semantic_settings,
            (
                "reranking",
                "lexical_overlap_weight",
            ),
            0.25,
        ),
        step="0.01",
        minimum=0,
    )

    semantic_rows += input_row(
        "Minimum token length",
        "semantic_minimum_token_length",
        nested_value(
            semantic_settings,
            (
                "reranking",
                "minimum_token_length",
            ),
            2,
        ),
        step="1",
        minimum=1,
    )

    semantic_rows += input_row(
        "Candidate multiplier",
        "semantic_candidate_multiplier",
        nested_value(
            semantic_settings,
            ("candidate_pool", "multiplier"),
            5,
        ),
        step="1",
        minimum=1,
    )

    semantic_rows += input_row(
        "Minimum candidates",
        "semantic_minimum_candidates",
        nested_value(
            semantic_settings,
            (
                "candidate_pool",
                "minimum_candidates",
            ),
            50,
        ),
        step="1",
        minimum=1,
    )

    semantic_rows += input_row(
        "IVFFlat probes",
        "semantic_ivfflat_probes",
        nested_value(
            semantic_settings,
            (
                "vector_search",
                "ivfflat_probes",
            ),
            10,
        ),
        step="1",
        minimum=1,
    )

    semantic_session_fields = [
        (
            "Current product weight",
            "semantic_session_current_product_weight",
            "current_product_weight",
            1.0,
            "0.01",
        ),
        (
            "Viewed product weight",
            "semantic_session_viewed_product_weight",
            "viewed_product_weight",
            0.70,
            "0.01",
        ),
        (
            "Cart product weight",
            "semantic_session_cart_product_weight",
            "cart_product_weight",
            0.90,
            "0.01",
        ),
        (
            "Maximum viewed seeds",
            "semantic_session_max_viewed_seeds",
            "max_viewed_seeds",
            5,
            "1",
        ),
        (
            "Maximum cart seeds",
            "semantic_session_max_cart_seeds",
            "max_cart_seeds",
            5,
            "1",
        ),
        (
            "Maximum total seeds",
            "semantic_session_max_total_seeds",
            "max_total_seeds",
            8,
            "1",
        ),
        (
            "Candidate multiplier",
            "semantic_session_candidate_multiplier",
            "candidate_multiplier",
            2,
            "1",
        ),
        (
            "Minimum candidates",
            "semantic_session_minimum_candidates",
            "minimum_candidates",
            10,
            "1",
        ),
    ]

    for label, name, key, default, step in semantic_session_fields:
        semantic_rows += input_row(
            label,
            name,
            nested_value(
                semantic_settings,
                ("session_recommendation", key),
                default,
            ),
            step=step,
            minimum=0,
        )

    elastic_rows = ""

    elastic_search_query = nested_value(
        elastic_settings,
        ("search_query",),
        {},
    )

    elastic_recommendation_query = nested_value(
        elastic_settings,
        ("recommendation_query",),
        {},
    )

    elastic_rows += select_row(
        "Search query type",
        "elastic_search_query_type",
        first_value(
            elastic_search_query,
            "type",
            default="best_fields",
        ),
        [
            ("best_fields", "Best Fields"),
            ("most_fields", "Most Fields"),
            ("cross_fields", "Cross Fields"),
        ],
    )

    elastic_rows += select_row(
        "Search operator",
        "elastic_search_operator",
        first_value(
            elastic_search_query,
            "operator",
            default="or",
        ),
        [
            ("or", "OR"),
            ("and", "AND"),
        ],
    )

    elastic_search_weight_fields = [
        ("Name weight", "elastic_search_name_weight", "name", 5),
        (
            "Category weight",
            "elastic_search_category_weight",
            "category",
            3,
        ),
        (
            "Description weight",
            "elastic_search_description_weight",
            "description",
            2,
        ),
        (
            "Material weight",
            "elastic_search_material_weight",
            "material",
            1,
        ),
        (
            "Color weight",
            "elastic_search_color_weight",
            "color",
            1,
        ),
        (
            "Size weight",
            "elastic_search_size_weight",
            "size",
            1,
        ),
        (
            "SKU weight",
            "elastic_search_sku_weight",
            "sku",
            2,
        ),
    ]

    for label, name, key, default in elastic_search_weight_fields:
        elastic_rows += input_row(
            label,
            name,
            nested_value(
                elastic_settings,
                (
                    "search_query",
                    "field_weights",
                    key,
                ),
                default,
            ),
            step="0.01",
            minimum=0,
        )

    elastic_rows += select_row(
        "Recommendation query type",
        "elastic_recommendation_query_type",
        first_value(
            elastic_recommendation_query,
            "type",
            default="best_fields",
        ),
        [
            ("best_fields", "Best Fields"),
            ("most_fields", "Most Fields"),
            ("cross_fields", "Cross Fields"),
        ],
    )

    elastic_rows += select_row(
        "Recommendation operator",
        "elastic_recommendation_operator",
        first_value(
            elastic_recommendation_query,
            "operator",
            default="or",
        ),
        [
            ("or", "OR"),
            ("and", "AND"),
        ],
    )

    elastic_recommendation_weight_fields = [
        (
            "Name weight",
            "elastic_recommendation_name_weight",
            "name",
            5,
        ),
        (
            "Category weight",
            "elastic_recommendation_category_weight",
            "category",
            4,
        ),
        (
            "Description weight",
            "elastic_recommendation_description_weight",
            "description",
            2,
        ),
        (
            "Material weight",
            "elastic_recommendation_material_weight",
            "material",
            2,
        ),
        (
            "Color weight",
            "elastic_recommendation_color_weight",
            "color",
            1,
        ),
        (
            "Size weight",
            "elastic_recommendation_size_weight",
            "size",
            1,
        ),
        (
            "SKU weight",
            "elastic_recommendation_sku_weight",
            "sku",
            2,
        ),
    ]

    for (
        label,
        name,
        key,
        default,
    ) in elastic_recommendation_weight_fields:
        elastic_rows += input_row(
            label,
            name,
            nested_value(
                elastic_settings,
                (
                    "recommendation_query",
                    "field_weights",
                    key,
                ),
                default,
            ),
            step="0.01",
            minimum=0,
        )

    elastic_rows += input_row(
        "Recommendation candidate multiplier",
        "elastic_recommendation_candidate_multiplier",
        nested_value(
            elastic_settings,
            (
                "recommendation_query",
                "candidate_multiplier",
            ),
            3,
        ),
        step="1",
        minimum=1,
    )

    elastic_rows += input_row(
        "Recommendation minimum candidates",
        "elastic_recommendation_minimum_candidates",
        nested_value(
            elastic_settings,
            (
                "recommendation_query",
                "minimum_candidates",
            ),
            20,
        ),
        step="1",
        minimum=1,
    )

    elastic_rows += checkbox_row(
        "Exclude source SKU",
        "elastic_recommendation_exclude_source_sku",
        nested_value(
            elastic_settings,
            (
                "recommendation_query",
                "exclude_source_sku",
            ),
            True,
        ),
    )

    elastic_session_fields = [
        (
            "Current product weight",
            "elastic_session_current_product_weight",
            "current_product_weight",
            1.0,
            "0.01",
        ),
        (
            "Viewed product weight",
            "elastic_session_viewed_product_weight",
            "viewed_product_weight",
            0.70,
            "0.01",
        ),
        (
            "Cart product weight",
            "elastic_session_cart_product_weight",
            "cart_product_weight",
            0.90,
            "0.01",
        ),
        (
            "Maximum viewed seeds",
            "elastic_session_max_viewed_seeds",
            "max_viewed_seeds",
            5,
            "1",
        ),
        (
            "Maximum cart seeds",
            "elastic_session_max_cart_seeds",
            "max_cart_seeds",
            5,
            "1",
        ),
        (
            "Maximum total seeds",
            "elastic_session_max_total_seeds",
            "max_total_seeds",
            8,
            "1",
        ),
        (
            "Candidate multiplier",
            "elastic_session_candidate_multiplier",
            "candidate_multiplier",
            2,
            "1",
        ),
        (
            "Minimum candidates",
            "elastic_session_minimum_candidates",
            "minimum_candidates",
            10,
            "1",
        ),
    ]

    for label, name, key, default, step in elastic_session_fields:
        elastic_rows += input_row(
            label,
            name,
            nested_value(
                elastic_settings,
                ("session_recommendation", key),
                default,
            ),
            step=step,
            minimum=0,
        )

    config_json = json.dumps(
        method_configs,
        ensure_ascii=False,
    ).replace("</", "<\\/")

    return f"""
    <div class="chart-card">
        <h2>Current Search Configuration</h2>

        <div class="notice" style="margin-bottom:20px;">
            Select a search method to edit its common and
            method-specific configuration. Saving activates
            the selected method.
        </div>

        <form
            method="post"
            action="/evaluation/update-config"
            id="evaluationConfigForm"
        >
            {section_table(
                "Common Search and Recommendation Settings",
                common_rows,
            )}

            {section_table(
                "Lexical Algorithm Settings",
                lexical_rows,
                "lexical",
            )}

            {section_table(
                "Semantic Vector Algorithm Settings",
                semantic_rows,
                "semantic_vector",
            )}

            {section_table(
                "Elasticsearch BM25 Algorithm Settings",
                elastic_rows,
                "elasticsearch_bm25",
            )}

            <div style="margin-top:20px;">
                <button type="submit">
                    Save selected configuration to BMS
                </button>
            </div>
        </form>

        <script>
            (function () {{
                const methodSelect =
                    document.getElementById('searchMethod');

                if (!methodSelect) {{
                    return;
                }}

                const configs = {config_json};

                const commonFields = {{
                    name: ['name'],
                    nameWeight: ['nameWeight', 'name_weight'],
                    descriptionWeight: [
                        'descriptionWeight',
                        'description_weight'
                    ],
                    categoryWeight: [
                        'categoryWeight',
                        'category_weight'
                    ],
                    materialWeight: [
                        'materialWeight',
                        'material_weight'
                    ],
                    colorWeight: [
                        'colorWeight',
                        'color_weight'
                    ],
                    sizeWeight: [
                        'sizeWeight',
                        'size_weight'
                    ],
                    attributesWeight: [
                        'attributesWeight',
                        'attributes_weight'
                    ],
                    sameCategoryBonus: [
                        'sameCategoryBonus',
                        'same_category_bonus'
                    ],
                    sameMaterialBonus: [
                        'sameMaterialBonus',
                        'same_material_bonus'
                    ],
                    sameColorBonus: [
                        'sameColorBonus',
                        'same_color_bonus'
                    ],
                    sameSizeBonus: [
                        'sameSizeBonus',
                        'same_size_bonus'
                    ],
                    sameCategoryRecommendationWeight: [
                        'sameCategoryRecommendationWeight',
                        'same_category_recommendation_weight'
                    ],
                    sameColorRecommendationWeight: [
                        'sameColorRecommendationWeight',
                        'same_color_recommendation_weight'
                    ],
                    sameSizeRecommendationWeight: [
                        'sameSizeRecommendationWeight',
                        'same_size_recommendation_weight'
                    ],
                    wishlistRecommendationWeight: [
                        'wishlistRecommendationWeight',
                        'wishlist_recommendation_weight'
                    ],
                    orderHistoryRecommendationWeight: [
                        'orderHistoryRecommendationWeight',
                        'order_history_recommendation_weight'
                    ],
                    searchHistoryRecommendationWeight: [
                        'searchHistoryRecommendationWeight',
                        'search_history_recommendation_weight'
                    ],
                    viewHistoryRecommendationWeight: [
                        'viewHistoryRecommendationWeight',
                        'view_history_recommendation_weight'
                    ],
                    maxRecommendationPerCategory: [
                        'maxRecommendationPerCategory',
                        'max_recommendation_per_category'
                    ],
                    recommendationDiversityPenalty: [
                        'recommendationDiversityPenalty',
                        'recommendation_diversity_penalty'
                    ],
                    recommendationEnabled: [
                        'recommendationEnabled',
                        'recommendation_enabled'
                    ],
                    recommendationLoggingEnabled: [
                        'recommendationLoggingEnabled',
                        'recommendation_logging_enabled'
                    ]
                }};

                function getConfigValue(config, keys) {{
                    for (const key of keys) {{
                        if (
                            Object.prototype.hasOwnProperty.call(
                                config,
                                key
                            )
                            && config[key] !== null
                            && config[key] !== undefined
                        ) {{
                            return config[key];
                        }}
                    }}

                    return undefined;
                }}

                function loadCommonConfig(method) {{
                    const config = configs[method] || {{}};

                    for (
                        const [elementId, keys]
                        of Object.entries(commonFields)
                    ) {{
                        const element =
                            document.getElementById(elementId);

                        if (!element) {{
                            continue;
                        }}

                        const value =
                            getConfigValue(config, keys);

                        if (value === undefined) {{
                            continue;
                        }}

                        if (element.type === 'checkbox') {{
                            element.checked = Boolean(value);
                        }} else {{
                            element.value = String(value);
                        }}
                    }}
                }}

                function showMethodSettings(method) {{
                    document
                        .querySelectorAll(
                            '.search-method-settings'
                        )
                        .forEach((section) => {{
                            const visible =
                                section.dataset.searchMethod
                                === method;

                            section.hidden = !visible;
                            section.style.display =
                                visible ? 'block' : 'none';
                        }});
                }}

                function updateMethod() {{
                    const method =
                        methodSelect.value || 'lexical';

                    loadCommonConfig(method);
                    showMethodSettings(method);
                }}

                methodSelect.addEventListener(
                    'change',
                    updateMethod
                );

                updateMethod();
            }})();
        </script>
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
            "lexical": [],
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

def build_recommendation_metrics_section(metrics):
    metrics = metrics or {}

    coverage = metrics.get("coverage", {})
    diversity = metrics.get("diversity", {})
    popularity = metrics.get("popularity_amplification", {})
    freshness = metrics.get("freshness", {})

    top_recommended = popularity.get("top_recommended", [])
    top_popular = popularity.get("top_popular", [])

    top_recommended_rows = ""

    for item in top_recommended:
        top_recommended_rows += f"""
            <tr>
                <td>{item.get("recommended_sku", "-")}</td>
                <td>{item.get("recommendation_count", 0)}</td>
            </tr>
        """

    top_popular_rows = ""

    for item in top_popular:
        top_popular_rows += f"""
            <tr>
                <td>{item.get("sku", "-")}</td>
                <td>{item.get("view_count", 0)}</td>
                <td>{item.get("sold_count", 0)}</td>
                <td>{item.get("popularity_score", 0)}</td>
            </tr>
        """

    if not top_recommended_rows:
        top_recommended_rows = """
            <tr>
                <td colspan="2">No recommendation data yet.</td>
            </tr>
        """

    if not top_popular_rows:
        top_popular_rows = """
            <tr>
                <td colspan="4">No popularity data yet.</td>
            </tr>
        """

    return f"""
        <h2>Recommendation Event Metrics</h2>

        <div class="notice" style="margin-bottom:20px;">
            These metrics are calculated from RecommendationEventLog impression and click data.
            They evaluate how recommendations behave inside the e-shop, not only search relevance.
        </div>

        <div class="metrics-grid">
            <div class="metric-card">
                <div class="metric-label">Coverage</div>
                <div class="metric-value">{coverage.get("coverage_percent", 0)}%</div>
                <div class="metric-subtitle">
                    {coverage.get("recommended_count", 0)}
                    /
                    {coverage.get("visible_count", 0)}
                    visible products
                </div>
            </div>

            <div class="metric-card">
                <div class="metric-label">Diversity</div>
                <div class="metric-value">{diversity.get("category_count", 0)}</div>
                <div class="metric-subtitle">
                    categories, top category share
                    {diversity.get("top_category_share_percent", 0)}%
                </div>
            </div>

            <div class="metric-card">
                <div class="metric-label">Popularity amplification</div>
                <div class="metric-value">{popularity.get("popularity_overlap_percent", 0)}%</div>
                <div class="metric-subtitle">
                    top overlap
                    {popularity.get("overlap_count", 0)}
                    /
                    {popularity.get("top_recommended_count", 0)}
                </div>
            </div>

            <div class="metric-card">
                <div class="metric-label">Freshness</div>
                <div class="metric-value">{freshness.get("avg_age_days", 0)} days</div>
                <div class="metric-subtitle">
                    {freshness.get("fresh_30d_percent", 0)}%
                    fresh within 30 days
                </div>
            </div>
        </div>

        <div class="two-column-grid" style="margin-top:24px;">
            <div class="card-inner">
                <h3>Top Recommended Products</h3>
                <table>
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Recommendation count</th>
                        </tr>
                    </thead>
                    <tbody>
                        {top_recommended_rows}
                    </tbody>
                </table>
            </div>

            <div class="card-inner">
                <h3>Top Popular Products</h3>
                <table>
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Views</th>
                            <th>Sold</th>
                            <th>Popularity score</th>
                        </tr>
                    </thead>
                    <tbody>
                        {top_popular_rows}
                    </tbody>
                </table>
            </div>
        </div>
    """

def render_evaluation_page(
    report,
    config=None,
    recommendation_log=None,
    recommendation_metrics=None,
    user_study_metrics=None,
):
    if config is None:
        config = {}

    if recommendation_metrics is None:
        recommendation_metrics = {}

    if user_study_metrics is None:

        user_study_metrics = {}

    body, search_chart = build_search_evaluation_body(report)

    return f"""
    <!doctype html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>Search Evaluation Report</title>
        {page_style()}
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

                {build_recommendation_metrics_section(recommendation_metrics)}

                {build_user_study_section(user_study_metrics)}

                {build_recommendation_log_section(recommendation_log)}

                {body}
            </div>
        </div>

        <script>
            const searchChartData = {json.dumps(search_chart)};

            if (searchChartData.labels.length > 0) {{
                new Chart(document.getElementById('searchTimeChart'), {{
                    type: 'bar',
                    data: {{
                        labels: searchChartData.labels,
                        datasets: [
                            {{
                                label: 'Lexical response time ms',
                                data: searchChartData.lexical
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