import json
import re
from typing import Any

from repositories.product_repository import (
    fetch_relevance_config_by_method,
)
from semantic_search.embedding_service import EmbeddingService
from semantic_search.semantic_vector_repository import (
    SemanticVectorRepository,
)


SEMANTIC_METHOD = "semantic_vector"


DEFAULT_DOCUMENT_FIELDS = {
    "name": True,
    "category": True,
    "description": True,
    "material": True,
    "color": True,
    "size": True,
    "attributes": False,
}

DEFAULT_TEXT_NORMALIZATION = {
    "lowercase": True,
    "replace_hyphen_with_space": True,
    "replace_underscore_with_space": True,
    "replace_unsupported_characters": True,
    "collapse_whitespace": True,
}

DEFAULT_RERANKING_SETTINGS = {
    "semantic_similarity_weight": 0.75,
    "lexical_overlap_weight": 0.25,
    "minimum_token_length": 2,
}

DEFAULT_CANDIDATE_POOL_SETTINGS = {
    "multiplier": 5,
    "minimum_candidates": 50,
}

DEFAULT_REINDEX_SETTINGS = {
    "batch_size": 64,
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

    if parsed < 1:
        return default

    return parsed


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


DEFAULT_VECTOR_SEARCH_SETTINGS = {
    "ivfflat_probes": 10,
}


class SemanticSearchService:
    def __init__(self):
        self.config = fetch_relevance_config_by_method(
            SEMANTIC_METHOD
        )

        self.repository = SemanticVectorRepository()

        self.embedding_service = EmbeddingService(
            self.config
        )

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
                return {}

        return {}

    def get_settings_section(
        self,
        section: str,
        defaults: dict | None = None,
    ) -> dict:
        result = dict(defaults or {})

        value = self.get_algorithm_settings().get(
            section,
            {},
        )

        if isinstance(value, dict):
            result.update(value)

        return result

    def get_ivfflat_probes(self) -> int:
        settings = self.get_settings_section(
            "vector_search",
            DEFAULT_VECTOR_SEARCH_SETTINGS,
        )

        return parse_positive_int(
            settings.get("ivfflat_probes"),
            DEFAULT_VECTOR_SEARCH_SETTINGS[
                "ivfflat_probes"
            ],
        )

    def reload_config(
        self,
        config: dict | None = None,
    ) -> dict:
        if config is None:
            config = fetch_relevance_config_by_method(
                "semantic_vector"
            )

        self.config = config

        self.embedding_service.reload_config(
            self.config
        )

        return self.config

    def ensure_storage(self) -> None:
        self.repository.ensure_table(
            self.embedding_service.get_dimension()
        )

    def normalize_text(
        self,
        text: str | None,
    ) -> str:
        if text is None:
            return ""

        settings = self.get_settings_section(
            "text_normalization",
            DEFAULT_TEXT_NORMALIZATION,
        )

        normalized_text = str(text)

        if parse_boolean(
            settings.get("lowercase"),
            True,
        ):
            normalized_text = normalized_text.lower()

        if parse_boolean(
            settings.get(
                "replace_hyphen_with_space"
            ),
            True,
        ):
            normalized_text = normalized_text.replace(
                "-",
                " ",
            )

        if parse_boolean(
            settings.get(
                "replace_underscore_with_space"
            ),
            True,
        ):
            normalized_text = normalized_text.replace(
                "_",
                " ",
            )

        if parse_boolean(
            settings.get(
                "replace_unsupported_characters"
            ),
            True,
        ):
            normalized_text = re.sub(
                r"[^a-z0-9#.+/ ]+",
                " ",
                normalized_text,
            )

        if parse_boolean(
            settings.get("collapse_whitespace"),
            True,
        ):
            normalized_text = re.sub(
                r"\s+",
                " ",
                normalized_text,
            )

        return normalized_text.strip()

    def tokenize(
        self,
        text: str,
    ) -> set[str]:
        reranking_settings = self.get_settings_section(
            "reranking",
            DEFAULT_RERANKING_SETTINGS,
        )

        minimum_token_length = parse_positive_int(
            reranking_settings.get(
                "minimum_token_length"
            ),
            2,
        )

        normalized_text = self.normalize_text(
            text
        )

        return {
            token
            for token in normalized_text.split()
            if len(token) >= minimum_token_length
        }

    def get_enabled_document_fields(self) -> dict:
        settings = self.get_settings_section(
            "document_fields",
            DEFAULT_DOCUMENT_FIELDS,
        )

        return {
            field: parse_boolean(
                settings.get(field),
                default,
            )
            for field, default
            in DEFAULT_DOCUMENT_FIELDS.items()
        }

    def product_value(
        self,
        product,
        key: str,
    ):
        if isinstance(product, dict):
            return product.get(key)

        return getattr(
            product,
            key,
            None,
        )

    def build_product_document(
        self,
        product,
    ) -> str:
        enabled_fields = (
            self.get_enabled_document_fields()
        )

        parts = []

        if enabled_fields["name"]:
            name = self.normalize_text(
                self.product_value(
                    product,
                    "name",
                )
            )

            if name:
                parts.append(
                    f"product title: {name}"
                )

        if enabled_fields["category"]:
            category = self.normalize_text(
                self.product_value(
                    product,
                    "category_name",
                )
            )

            if category:
                parts.append(
                    f"product category: {category}"
                )

        if enabled_fields["description"]:
            description = self.normalize_text(
                self.product_value(
                    product,
                    "description",
                )
            )

            if description:
                parts.append(
                    f"product description: {description}"
                )

        if enabled_fields["material"]:
            material = self.normalize_text(
                self.product_value(
                    product,
                    "material",
                )
            )

            if material:
                parts.append(
                    f"material: {material}"
                )

        if enabled_fields["color"]:
            color = self.normalize_text(
                self.product_value(
                    product,
                    "color_name",
                )
            )

            if color:
                parts.append(
                    f"color: {color}"
                )

        if enabled_fields["size"]:
            size = self.normalize_text(
                self.product_value(
                    product,
                    "size_name",
                )
            )

            if size:
                parts.append(
                    f"size: {size}"
                )

        # 只有 repository 查询返回 attributes 时，
        # 这个配置才会真正产生内容。
        if enabled_fields["attributes"]:
            attributes = self.normalize_text(
                self.product_value(
                    product,
                    "attributes",
                )
            )

            if attributes:
                parts.append(
                    f"attributes: {attributes}"
                )

        return ". ".join(parts)

    def build_result_text(
        self,
        result: dict,
    ) -> str:
        enabled_fields = (
            self.get_enabled_document_fields()
        )

        parts = []

        field_mapping = {
            "name": "name",
            "category": "category_name",
            "description": "description",
            "material": "material",
            "color": "color_name",
            "size": "size_name",
            "attributes": "attributes",
        }

        for config_field, result_field in (
            field_mapping.items()
        ):
            if not enabled_fields.get(
                config_field,
                False,
            ):
                continue

            value = result.get(
                result_field,
                "",
            )

            if value:
                parts.append(str(value))

        return " ".join(parts)

    def lexical_overlap_score(
        self,
        query: str,
        result: dict,
    ) -> float:
        query_tokens = self.tokenize(
            query
        )

        product_text = self.build_result_text(
            result
        )

        product_tokens = self.tokenize(
            product_text
        )

        if not query_tokens or not product_tokens:
            return 0.0

        matching_tokens = (
            query_tokens
            & product_tokens
        )

        return (
            len(matching_tokens)
            / len(query_tokens)
        )

    def rerank_results(
        self,
        query: str,
        results: list[dict],
        limit: int,
    ) -> list[dict]:
        settings = self.get_settings_section(
            "reranking",
            DEFAULT_RERANKING_SETTINGS,
        )

        semantic_weight = (
            parse_non_negative_float(
                settings.get(
                    "semantic_similarity_weight"
                ),
                0.75,
            )
        )

        lexical_weight = (
            parse_non_negative_float(
                settings.get(
                    "lexical_overlap_weight"
                ),
                0.25,
            )
        )

        if (
            semantic_weight == 0
            and lexical_weight == 0
        ):
            semantic_weight = 0.75
            lexical_weight = 0.25

        for row in results:
            semantic_score = float(
                row.get(
                    "similarity",
                    0,
                )
            )

            lexical_score = (
                self.lexical_overlap_score(
                    query,
                    row,
                )
            )

            row["lexical_overlap"] = (
                lexical_score
            )

            row["final_score"] = (
                semantic_score
                * semantic_weight
                + lexical_score
                * lexical_weight
            )

        results.sort(
            key=lambda item: float(
                item.get(
                    "final_score",
                    0,
                )
            ),
            reverse=True,
        )

        return results[:limit]

    def reindex(self) -> dict:
        self.ensure_storage()

        products = (
            self.repository
            .get_products_for_indexing()
        )

        items = []

        for product in products:
            if isinstance(product, dict):
                product_id = product.get("id")
            else:
                product_id = product[0]

            if product_id is None:
                continue

            document = (
                self.build_product_document(
                    product
                )
            )

            if not document:
                continue

            items.append({
                "product_id": product_id,
                "document": document,
            })

        reindex_settings = (
            self.get_settings_section(
                "reindex",
                DEFAULT_REINDEX_SETTINGS,
            )
        )

        batch_size = parse_positive_int(
            reindex_settings.get(
                "batch_size"
            ),
            64,
        )

        indexed_count = 0

        for start in range(
            0,
            len(items),
            batch_size,
        ):
            batch = items[
                start:start + batch_size
            ]

            documents = [
                item["document"]
                for item in batch
            ]

            embeddings = (
                self.embedding_service
                .create_embeddings(
                    documents
                )
            )

            for item, embedding in zip(
                batch,
                embeddings,
            ):
                pgvector = (
                    self.embedding_service
                    .to_pgvector(
                        embedding
                    )
                )

                self.repository.save_embedding(
                    item["product_id"],
                    pgvector,
                )

                indexed_count += 1

        return {
            "status": "ok",
            "indexed_products": indexed_count,
            "batch_size": batch_size,
        }

    def reindex_product_by_sku(
        self,
        sku: str,
    ) -> dict:
        self.ensure_storage()

        sku = str(
            sku or ""
        ).strip()

        if not sku:
            return {
                "status": "error",
                "message": "SKU is empty",
                "updated": 0,
                "deleted": 0,
            }

        product = (
            self.repository
            .get_product_for_indexing_by_sku(
                sku
            )
        )

        if product is None:
            deleted = (
                self.repository
                .delete_embedding_by_sku(
                    sku
                )
            )

            return {
                "status": "ok",
                "mode": "semantic_partial",
                "sku": sku,
                "updated": 0,
                "deleted": deleted,
                "message": (
                    "Product not found, old semantic "
                    "vector deleted if it existed."
                ),
            }

        if isinstance(product, dict):
            product_id = product.get("id")
        else:
            product_id = product[0]

        document = self.build_product_document(
            product
        )

        if not document:
            deleted = (
                self.repository
                .delete_embedding_by_product_id(
                    product_id
                )
            )

            return {
                "status": "ok",
                "mode": "semantic_partial",
                "sku": sku,
                "product_id": product_id,
                "updated": 0,
                "deleted": deleted,
                "message": (
                    "Product document is empty, "
                    "semantic vector deleted."
                ),
            }

        embedding = (
            self.embedding_service
            .create_embedding(
                document
            )
        )

        pgvector = (
            self.embedding_service
            .to_pgvector(
                embedding
            )
        )

        self.repository.save_embedding(
            product_id,
            pgvector,
        )

        return {
            "status": "ok",
            "mode": "semantic_partial",
            "sku": sku,
            "product_id": product_id,
            "updated": 1,
            "deleted": 0,
            "document": document,
        }

    def search(
        self,
        query: str,
        limit: int = 10,
    ) -> dict:
        self.ensure_storage()

        limit = parse_positive_int(
            limit,
            10,
        )

        normalized_query = (
            self.normalize_text(
                query
            )
        )

        if not normalized_query:
            return {
                "method": (
                    "semantic_vector_search_optimized"
                ),
                "query": query,
                "normalized_query": "",
                "limit": limit,
                "candidate_limit": 0,
                "results": [],
            }

        embedding = (
            self.embedding_service
            .create_embedding(
                normalized_query
            )
        )

        pgvector = (
            self.embedding_service
            .to_pgvector(
                embedding
            )
        )

        candidate_settings = (
            self.get_settings_section(
                "candidate_pool",
                DEFAULT_CANDIDATE_POOL_SETTINGS,
            )
        )

        candidate_multiplier = (
            parse_positive_int(
                candidate_settings.get(
                    "multiplier"
                ),
                5,
            )
        )

        minimum_candidates = (
            parse_positive_int(
                candidate_settings.get(
                    "minimum_candidates"
                ),
                50,
            )
        )

        candidate_limit = max(
            limit * candidate_multiplier,
            minimum_candidates,
        )

        ivfflat_probes = self.get_ivfflat_probes()

        results = (
            self.repository.search_by_vector(
                pgvector,
                candidate_limit,
                ivfflat_probes,
            )
        )

        results = self.rerank_results(
            normalized_query,
            results,
            limit,
        )

        return {
            "method": (
                "semantic_vector_search_optimized"
            ),
            "query": query,
            "normalized_query": normalized_query,
            "limit": limit,
            "candidate_limit": candidate_limit,
            "ivfflat_probes": ivfflat_probes,
            "results": results,
        }

    def similar_products(
        self,
        product_id: int,
        limit: int = 10,
    ) -> dict:
        self.ensure_storage()
        ivfflat_probes = self.get_ivfflat_probes()

        if not parse_boolean(
            self.config.get(
                "recommendation_enabled"
            ),
            True,
        ):
            return {
                "method": (
                    "semantic_vector_similarity"
                ),
                "product_id": product_id,
                "limit": limit,
                "results": [],
            }

        limit = parse_positive_int(
            limit,
            10,
        )

        product_vector = (
            self.repository
            .get_product_vector(
                product_id
            )
        )

        if product_vector is None:
            return {
                "method": (
                    "semantic_vector_similarity"
                ),
                "product_id": product_id,
                "error": (
                    "Product vector not found. "
                    "Please run semantic reindex first."
                ),
                "results": [],
            }

        results = (
            self.repository
            .find_similar_products(
                product_id,
                product_vector,
                limit,
                ivfflat_probes,
            )
        )

        return {
            "method": (
                "semantic_vector_similarity"
            ),
            "product_id": product_id,
            "limit": limit,
            "ivfflat_probes": ivfflat_probes,
            "results": results,
        }