import json
import logging
from typing import Any

from elasticsearch import Elasticsearch
from elasticsearch.exceptions import NotFoundError

from config import settings
from repositories.product_repository import (
    fetch_latest_product_by_sku,
    fetch_relevance_config_by_method,
)


logger = logging.getLogger(__name__)

INDEX_NAME = "products"
ELASTICSEARCH_METHOD = "elasticsearch_bm25"


DEFAULT_SEARCH_QUERY_SETTINGS = {
    "type": "best_fields",
    "operator": "or",
    "field_weights": {
        "name": 5,
        "category": 3,
        "description": 2,
        "material": 1,
        "color": 1,
        "size": 1,
        "sku": 2,
    },
}


DEFAULT_RECOMMENDATION_QUERY_SETTINGS = {
    "type": "best_fields",
    "operator": "or",
    "field_weights": {
        "name": 5,
        "category": 4,
        "description": 2,
        "material": 2,
        "color": 1,
        "size": 1,
        "sku": 2,
    },
    "candidate_multiplier": 3,
    "minimum_candidates": 20,
    "exclude_source_sku": True,
}


SUPPORTED_MULTI_MATCH_TYPES = {
    "best_fields",
    "most_fields",
    "cross_fields",
    "phrase",
    "phrase_prefix",
    "bool_prefix",
}


def parse_boolean(
    value: Any,
    default: bool,
) -> bool:
    if isinstance(value, bool):
        return value

    if isinstance(value, int):
        return value != 0

    if isinstance(value, str):
        normalized = value.strip().lower()

        if normalized in {
            "true",
            "1",
            "yes",
            "on",
        }:
            return True

        if normalized in {
            "false",
            "0",
            "no",
            "off",
        }:
            return False

    return default


def parse_positive_int(
    value: Any,
    default: int,
) -> int:
    try:
        parsed = int(value)
    except (TypeError, ValueError):
        return default

    return parsed if parsed > 0 else default


def parse_non_negative_float(
    value: Any,
    default: float,
) -> float:
    try:
        parsed = float(value)
    except (TypeError, ValueError):
        return default

    if parsed < 0:
        return default

    return parsed


class ElasticProductSearchService:
    def __init__(self):
        self.client = Elasticsearch(
            settings.elasticsearch_url
        )

        self.config = (
            fetch_relevance_config_by_method(
                ELASTICSEARCH_METHOD
            )
        )

    def reload_config(
        self,
        config: dict | None = None,
    ) -> dict:
        if config is None:
            config = (
                fetch_relevance_config_by_method(
                    ELASTICSEARCH_METHOD
                )
            )

        self.config = (
            config
            if isinstance(config, dict)
            else {}
        )

        logger.info(
            "[CONFIG][ELASTICSEARCH] configuration reloaded"
        )

        return self.config

    def get_algorithm_settings(self) -> dict:
        value = self.config.get(
            "algorithm_settings",
            {},
        )

        if isinstance(value, dict):
            return value

        if isinstance(value, str):
            try:
                parsed = json.loads(value)

                if isinstance(parsed, dict):
                    return parsed

            except json.JSONDecodeError:
                logger.warning(
                    "[CONFIG][ELASTICSEARCH] invalid "
                    "algorithm_settings JSON"
                )

        return {}

    def get_settings_section(
        self,
        section: str,
        defaults: dict,
    ) -> dict:
        result = dict(defaults)

        value = self.get_algorithm_settings().get(
            section,
            {},
        )

        if isinstance(value, dict):
            result.update(value)

        default_field_weights = defaults.get(
            "field_weights",
            {},
        )

        configured_field_weights = (
            value.get("field_weights", {})
            if isinstance(value, dict)
            else {}
        )

        if isinstance(default_field_weights, dict):
            merged_field_weights = dict(
                default_field_weights
            )

            if isinstance(
                configured_field_weights,
                dict,
            ):
                merged_field_weights.update(
                    configured_field_weights
                )

            result["field_weights"] = (
                merged_field_weights
            )

        return result

    @staticmethod
    def product_value(
        product,
        *keys: str,
        default=None,
    ):
        for key in keys:
            if isinstance(product, dict):
                value = product.get(key)
            else:
                value = getattr(
                    product,
                    key,
                    None,
                )

            if value is not None:
                return value

        return default

    @staticmethod
    def normalize_query_type(
        value: Any,
        default: str = "best_fields",
    ) -> str:
        normalized = str(
            value or ""
        ).strip()

        if normalized not in SUPPORTED_MULTI_MATCH_TYPES:
            return default

        return normalized

    @staticmethod
    def normalize_operator(
        value: Any,
        default: str = "or",
    ) -> str:
        normalized = str(
            value or ""
        ).strip().lower()

        if normalized not in {
            "and",
            "or",
        }:
            return default

        return normalized

    def build_weighted_fields(
        self,
        field_weights: dict,
        fallback: dict,
    ) -> list[str]:
        merged_weights = dict(fallback)

        if isinstance(field_weights, dict):
            merged_weights.update(field_weights)

        fields = []

        for field_name in (
            "name",
            "category",
            "description",
            "material",
            "color",
            "size",
            "sku",
        ):
            default_weight = float(
                fallback.get(
                    field_name,
                    1,
                )
            )

            weight = parse_non_negative_float(
                merged_weights.get(field_name),
                default_weight,
            )

            if weight <= 0:
                continue

            if weight == 1:
                fields.append(field_name)
            else:
                fields.append(
                    f"{field_name}^{weight:g}"
                )

        if not fields:
            return [
                "name",
                "description",
                "category",
            ]

        return fields

    def get_search_query_settings(self) -> dict:
        settings = self.get_settings_section(
            "search_query",
            DEFAULT_SEARCH_QUERY_SETTINGS,
        )

        settings["type"] = (
            self.normalize_query_type(
                settings.get("type"),
                "best_fields",
            )
        )

        settings["operator"] = (
            self.normalize_operator(
                settings.get("operator"),
                "or",
            )
        )

        return settings

    def get_recommendation_query_settings(
        self,
    ) -> dict:
        settings = self.get_settings_section(
            "recommendation_query",
            DEFAULT_RECOMMENDATION_QUERY_SETTINGS,
        )

        settings["type"] = (
            self.normalize_query_type(
                settings.get("type"),
                "best_fields",
            )
        )

        settings["operator"] = (
            self.normalize_operator(
                settings.get("operator"),
                "or",
            )
        )

        settings["candidate_multiplier"] = (
            parse_positive_int(
                settings.get(
                    "candidate_multiplier"
                ),
                3,
            )
        )

        settings["minimum_candidates"] = (
            parse_positive_int(
                settings.get(
                    "minimum_candidates"
                ),
                20,
            )
        )

        settings["exclude_source_sku"] = (
            parse_boolean(
                settings.get(
                    "exclude_source_sku"
                ),
                True,
            )
        )

        return settings

    def create_index(self) -> None:
        if self.index_exists():
            self.client.indices.delete(
                index=INDEX_NAME
            )

        self.client.indices.create(
            index=INDEX_NAME,
            mappings={
                "properties": {
                    "product_id": {
                        "type": "integer",
                    },
                    "sku": {
                        "type": "keyword",
                    },
                    "name": {
                        "type": "text",
                    },
                    "description": {
                        "type": "text",
                    },
                    "category": {
                        "type": "text",
                    },
                    "material": {
                        "type": "text",
                    },
                    "color": {
                        "type": "text",
                    },
                    "size": {
                        "type": "text",
                    },
                    "price": {
                        "type": "float",
                    },
                }
            },
        )

        logger.info(
            "[ELASTICSEARCH][INDEX] index created"
        )

    def build_document(
        self,
        product,
    ) -> dict:
        product_id = self.product_value(
            product,
            "id",
        )

        sku = str(
            self.product_value(
                product,
                "sku",
                default="",
            )
            or ""
        ).strip()

        return {
            "product_id": product_id,
            "sku": sku,
            "name": str(
                self.product_value(
                    product,
                    "name",
                    default="",
                )
                or ""
            ),
            "description": str(
                self.product_value(
                    product,
                    "description",
                    default="",
                )
                or ""
            ),
            "category": str(
                self.product_value(
                    product,
                    "category_name",
                    "category",
                    default="",
                )
                or ""
            ),
            "material": str(
                self.product_value(
                    product,
                    "material",
                    default="",
                )
                or ""
            ),
            "color": str(
                self.product_value(
                    product,
                    "color_name",
                    "color",
                    default="",
                )
                or ""
            ),
            "size": str(
                self.product_value(
                    product,
                    "size_name",
                    "size",
                    default="",
                )
                or ""
            ),
            "price": float(
                self.product_value(
                    product,
                    "price",
                    default=0,
                )
                or 0
            ),
        }

    def index_products(
        self,
        products,
    ) -> int:
        indexed_count = 0

        for product in products:
            document = self.build_document(
                product
            )

            product_id = document.get(
                "product_id"
            )

            sku = str(
                document.get("sku") or ""
            ).strip()

            if product_id is None or not sku:
                continue

            self.client.index(
                index=INDEX_NAME,
                id=product_id,
                document=document,
            )

            indexed_count += 1

        self.client.indices.refresh(
            index=INDEX_NAME
        )

        logger.info(
            "[ELASTICSEARCH][INDEX] indexed=%s",
            indexed_count,
        )

        return indexed_count

    def delete_documents_by_sku(
        self,
        sku: str,
    ) -> int:
        sku = str(
            sku or ""
        ).strip()

        if not sku or not self.index_exists():
            return 0

        response = self.client.delete_by_query(
            index=INDEX_NAME,
            query={
                "term": {
                    "sku": sku,
                }
            },
            refresh=True,
            conflicts="proceed",
        )

        return int(
            response.get(
                "deleted",
                0,
            )
        )

    def reindex_product_by_sku(
        self,
        sku: str,
    ) -> dict:
        sku = str(
            sku or ""
        ).strip()

        if not sku:
            return {
                "status": "error",
                "method": ELASTICSEARCH_METHOD,
                "sku": sku,
                "updated": 0,
                "deleted": 0,
                "message": "SKU is empty.",
            }

        if not self.index_exists():
            self.client.indices.create(
                index=INDEX_NAME,
                mappings={
                    "properties": {
                        "product_id": {
                            "type": "integer",
                        },
                        "sku": {
                            "type": "keyword",
                        },
                        "name": {
                            "type": "text",
                        },
                        "description": {
                            "type": "text",
                        },
                        "category": {
                            "type": "text",
                        },
                        "material": {
                            "type": "text",
                        },
                        "color": {
                            "type": "text",
                        },
                        "size": {
                            "type": "text",
                        },
                        "price": {
                            "type": "float",
                        },
                    }
                },
            )

        deleted = self.delete_documents_by_sku(
            sku
        )

        product = fetch_latest_product_by_sku(
            sku
        )

        if not product or bool(
            product.get("hidden")
        ):
            return {
                "status": "ok",
                "method": ELASTICSEARCH_METHOD,
                "mode": "partial",
                "sku": sku,
                "updated": 0,
                "deleted": deleted,
                "message": (
                    "Product not found or hidden. "
                    "Existing Elasticsearch documents "
                    "were removed."
                ),
            }

        document = self.build_document(
            product
        )

        product_id = document.get(
            "product_id"
        )

        if product_id is None:
            return {
                "status": "error",
                "method": ELASTICSEARCH_METHOD,
                "mode": "partial",
                "sku": sku,
                "updated": 0,
                "deleted": deleted,
                "message": "Product ID is missing.",
            }

        self.client.index(
            index=INDEX_NAME,
            id=product_id,
            document=document,
            refresh=True,
        )

        return {
            "status": "ok",
            "method": ELASTICSEARCH_METHOD,
            "mode": "partial",
            "sku": sku,
            "product_id": product_id,
            "updated": 1,
            "deleted": deleted,
        }

    def search(
        self,
        query: str,
        limit: int = 10,
    ) -> dict:
        query = str(
            query or ""
        ).strip()

        limit = parse_positive_int(
            limit,
            10,
        )

        if not query:
            return {
                "method": ELASTICSEARCH_METHOD,
                "query": query,
                "limit": limit,
                "results": [],
            }

        query_settings = (
            self.get_search_query_settings()
        )

        fields = self.build_weighted_fields(
            query_settings.get(
                "field_weights",
                {},
            ),
            DEFAULT_SEARCH_QUERY_SETTINGS[
                "field_weights"
            ],
        )

        response = self.client.search(
            index=INDEX_NAME,
            size=limit,
            query={
                "multi_match": {
                    "query": query,
                    "fields": fields,
                    "type": query_settings["type"],
                    "operator": (
                        query_settings["operator"]
                    ),
                }
            },
        )

        results = []

        for hit in response.get(
            "hits",
            {},
        ).get(
            "hits",
            [],
        ):
            source = hit.get(
                "_source",
                {},
            )

            sku = str(
                source.get("sku") or ""
            ).strip()

            if not sku:
                continue

            results.append({
                "id": source.get(
                    "product_id"
                ),
                "name": source.get(
                    "name"
                ),
                "description": source.get(
                    "description"
                ),
                "price": source.get(
                    "price"
                ),
                "sku": sku,
                "product_sku": sku,
                "similarity": float(
                    hit.get(
                        "_score",
                        0,
                    )
                    or 0
                ),
                "method": ELASTICSEARCH_METHOD,
            })

        return {
            "method": ELASTICSEARCH_METHOD,
            "query": query,
            "limit": limit,
            "fields": fields,
            "results": results,
        }

    def recommend_by_sku(
        self,
        sku: str,
        limit: int = 10,
    ) -> dict:
        sku = str(
            sku or ""
        ).strip()

        limit = parse_positive_int(
            limit,
            10,
        )

        if not parse_boolean(
            self.config.get(
                "recommendation_enabled"
            ),
            True,
        ):
            return {
                "method": (
                    "elasticsearch_bm25_recommendation"
                ),
                "sku": sku,
                "limit": limit,
                "results": [],
            }

        if not sku:
            return {
                "method": (
                    "elasticsearch_bm25_recommendation"
                ),
                "sku": sku,
                "limit": limit,
                "results": [],
            }

        product = fetch_latest_product_by_sku(
            sku
        )

        if not product or bool(
            product.get("hidden")
        ):
            return {
                "method": (
                    "elasticsearch_bm25_recommendation"
                ),
                "sku": sku,
                "limit": limit,
                "results": [],
            }

        query_parts = [
            str(
                product.get("name") or ""
            ),
            str(
                product.get("category") or ""
            ),
            str(
                product.get("description") or ""
            ),
            str(
                product.get("material") or ""
            ),
            str(
                product.get("color") or ""
            ),
            str(
                product.get("size") or ""
            ),
            str(
                product.get("sku") or ""
            ),
        ]

        query_text = " ".join(
            part.strip()
            for part in query_parts
            if part.strip()
        )

        if not query_text:
            return {
                "method": (
                    "elasticsearch_bm25_recommendation"
                ),
                "sku": sku,
                "limit": limit,
                "results": [],
            }

        query_settings = (
            self.get_recommendation_query_settings()
        )

        fields = self.build_weighted_fields(
            query_settings.get(
                "field_weights",
                {},
            ),
            DEFAULT_RECOMMENDATION_QUERY_SETTINGS[
                "field_weights"
            ],
        )

        candidate_limit = max(
            limit
            * query_settings[
                "candidate_multiplier"
            ],
            query_settings[
                "minimum_candidates"
            ],
        )

        bool_query = {
            "must": [
                {
                    "multi_match": {
                        "query": query_text,
                        "fields": fields,
                        "type": query_settings[
                            "type"
                        ],
                        "operator": query_settings[
                            "operator"
                        ],
                    }
                }
            ],
        }

        if query_settings[
            "exclude_source_sku"
        ]:
            bool_query["must_not"] = [
                {
                    "term": {
                        "sku": sku,
                    }
                }
            ]

        response = self.client.search(
            index=INDEX_NAME,
            size=candidate_limit,
            query={
                "bool": bool_query
            },
        )

        results = []

        for hit in response.get(
            "hits",
            {},
        ).get(
            "hits",
            [],
        ):
            source = hit.get(
                "_source",
                {},
            )

            result_sku = str(
                source.get("sku") or ""
            ).strip()

            if not result_sku:
                continue

            if (
                query_settings[
                    "exclude_source_sku"
                ]
                and result_sku == sku
            ):
                continue

            results.append({
                "id": source.get(
                    "product_id"
                ),
                "name": source.get(
                    "name"
                ),
                "description": source.get(
                    "description"
                ),
                "price": source.get(
                    "price"
                ),
                "sku": result_sku,
                "product_sku": result_sku,
                "similarity": float(
                    hit.get(
                        "_score",
                        0,
                    )
                    or 0
                ),
                "method": (
                    "elasticsearch_bm25_recommendation"
                ),
            })

            if len(results) >= limit:
                break

        return {
            "method": (
                "elasticsearch_bm25_recommendation"
            ),
            "sku": sku,
            "limit": limit,
            "candidate_limit": candidate_limit,
            "fields": fields,
            "results": results,
        }

    def index_exists(self) -> bool:
        return bool(
            self.client.indices.exists(
                index=INDEX_NAME
            )
        )

    def count_indexed_products(self) -> int:
        if not self.index_exists():
            return 0

        response = self.client.count(
            index=INDEX_NAME
        )

        return int(
            response.get(
                "count",
                0,
            )
        )

    def ensure_index_ready(self) -> None:
        if self.count_indexed_products() > 0:
            return

        products = (
            self.repository_products_for_indexing()
        )

        self.create_index()
        self.index_products(products)

    def repository_products_for_indexing(
        self,
    ):
        from semantic_search.semantic_vector_repository import (
            SemanticVectorRepository,
        )

        repository = (
            SemanticVectorRepository()
        )

        return (
            repository
            .get_products_for_indexing()
        )