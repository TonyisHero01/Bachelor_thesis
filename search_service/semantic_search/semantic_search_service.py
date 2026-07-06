from semantic_search.embedding_service import EmbeddingService
from semantic_search.semantic_vector_repository import SemanticVectorRepository
from repositories.product_repository import fetch_active_relevance_config
import re


class SemanticSearchService:
    def __init__(self):
        self.repository = SemanticVectorRepository()
        self.embedding_service = EmbeddingService()
        self.config = fetch_active_relevance_config()

    def ensure_storage(self):
        self.repository.ensure_table(
            self.embedding_service.get_dimension()
        )

    def normalize_text(self, text: str):
        if text is None:
            return ""

        text = str(text).lower()
        text = text.replace("-", " ")
        text = text.replace("_", " ")
        text = re.sub(r"[^a-z0-9#.+/ ]+", " ", text)
        text = re.sub(r"\s+", " ", text).strip()

        return text

    def tokenize(self, text: str):
        text = self.normalize_text(text)

        return {
            token
            for token in text.split()
            if len(token) >= 2
        }

    def lexical_overlap_score(self, query: str, result: dict):
        query_tokens = self.tokenize(query)

        product_text = " ".join([
            str(result.get("name", "")),
            str(result.get("description", "")),
            str(result.get("category_name", "")),
            str(result.get("material", "")),
            str(result.get("color_name", "")),
            str(result.get("size_name", "")),
        ])

        product_tokens = self.tokenize(product_text)

        if not query_tokens or not product_tokens:
            return 0.0

        return len(query_tokens & product_tokens) / len(query_tokens)

    def rerank_results(self, query: str, results: list[dict], limit: int):
        for row in results:
            semantic_score = float(row.get("similarity", 0))
            lexical_score = self.lexical_overlap_score(query, row)

            row["lexical_overlap"] = lexical_score
            row["final_score"] = (
                semantic_score * 0.75
                + lexical_score * 0.25
            )

        results.sort(
            key=lambda item: item["final_score"],
            reverse=True,
        )

        return results[:limit]

    def build_product_document(self, product):
        def value(key):
            if isinstance(product, dict):
                return product.get(key)
            return getattr(product, key, None)

        name = self.normalize_text(value("name"))
        description = self.normalize_text(value("description"))
        material = self.normalize_text(value("material"))
        category = self.normalize_text(value("category_name"))
        color = self.normalize_text(value("color_name"))
        size = self.normalize_text(value("size_name"))

        parts = []

        if name:
            parts.append(f"product title: {name}")

        if category:
            parts.append(f"product category: {category}")

        if description:
            parts.append(f"product description: {description}")

        if material:
            parts.append(f"material: {material}")

        if color:
            parts.append(f"color: {color}")

        if size:
            parts.append(f"size: {size}")

        return ". ".join(parts)

    def reload_config(self):
        self.config = fetch_active_relevance_config()
        return self.config

    def apply_score_weights(self, results):
        semantic_weight = float(self.config.get("semantic_vector_weight", 1.0))

        for row in results:
            row["similarity"] = float(row["similarity"]) * semantic_weight

        results.sort(
            key=lambda item: item["similarity"],
            reverse=True
        )

        return results

    def reindex(self):
        self.ensure_storage()

        products = self.repository.get_products_for_indexing()

        items = []

        for product in products:
            product_id = product["id"] if isinstance(product, dict) else product[0]
            document = self.build_product_document(product)

            if document == "":
                continue

            items.append({
                "product_id": product_id,
                "document": document,
            })

        batch_size = 64
        indexed_count = 0

        for start in range(0, len(items), batch_size):
            batch = items[start:start + batch_size]

            documents = [
                item["document"]
                for item in batch
            ]

            embeddings = self.embedding_service.create_embeddings(documents)

            for item, embedding in zip(batch, embeddings):
                pgvector = self.embedding_service.to_pgvector(embedding)
                self.repository.save_embedding(
                    item["product_id"],
                    pgvector,
                )
                indexed_count += 1

        return {
            "status": "ok",
            "indexed_products": indexed_count,
        }
    
    def reindex_product_by_sku(self, sku: str):
        self.ensure_storage()

        sku = str(sku or "").strip()

        if sku == "":
            return {
                "status": "error",
                "message": "SKU is empty",
                "updated": 0,
                "deleted": 0,
            }

        product = self.repository.get_product_for_indexing_by_sku(sku)

        if product is None:
            self.repository.delete_embedding_by_sku(sku)

            return {
                "status": "ok",
                "mode": "semantic_partial",
                "sku": sku,
                "updated": 0,
                "deleted": 1,
                "message": "Product not found, old semantic vector deleted if it existed.",
            }

        product_id = product["id"] if isinstance(product, dict) else product[0]

        document = self.build_product_document(product)

        if document == "":
            self.repository.delete_embedding_by_product_id(product_id)

            return {
                "status": "ok",
                "mode": "semantic_partial",
                "sku": sku,
                "product_id": product_id,
                "updated": 0,
                "deleted": 1,
                "message": "Product document is empty, semantic vector deleted.",
            }

        embedding = self.embedding_service.create_embedding(document)
        pgvector = self.embedding_service.to_pgvector(embedding)

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

    def search(self, query: str, limit: int = 10):
        self.ensure_storage()

        normalized_query = self.normalize_text(query)

        embedding = self.embedding_service.create_embedding(normalized_query)
        pgvector = self.embedding_service.to_pgvector(embedding)

        candidate_limit = max(limit * 5, 50)

        results = self.repository.search_by_vector(
            pgvector,
            candidate_limit,
        )

        results = self.apply_score_weights(results)
        results = self.rerank_results(
            normalized_query,
            results,
            limit,
        )

        return {
            "method": "semantic_vector_search_optimized",
            "query": query,
            "normalized_query": normalized_query,
            "limit": limit,
            "candidate_limit": candidate_limit,
            "results": results,
        }

    def similar_products(self, product_id: int, limit: int = 10):
        self.ensure_storage()

        product_vector = self.repository.get_product_vector(product_id)

        if product_vector is None:
            return {
                "method": "semantic_vector_similarity",
                "product_id": product_id,
                "error": "Product vector not found. Please run semantic reindex first.",
                "results": []
            }

        results = self.repository.find_similar_products(
            product_id,
            product_vector,
            limit
        )

        return {
            "method": "semantic_vector_similarity",
            "product_id": product_id,
            "limit": limit,
            "results": results
        }