from semantic_search.embedding_service import EmbeddingService
from semantic_search.semantic_vector_repository import SemanticVectorRepository
from repositories.product_repository import fetch_active_relevance_config

class SemanticSearchService:
    def __init__(self):
        self.repository = SemanticVectorRepository()
        self.embedding_service = EmbeddingService()
        self.config = fetch_active_relevance_config()

    def build_product_document(self, product):
        def value(key):
            if isinstance(product, dict):
                return product.get(key)
            return getattr(product, key, None)

        parts = [
            value("name"),
            value("description"),
            value("material"),
            value("category_name"),
            value("color_name"),
            value("size_name"),
            value("width"),
            value("height"),
            value("length"),
            value("weight"),
            value("price"),
            value("sku"),
        ]

        return " ".join(
            str(part).strip()
            for part in parts
            if part is not None and str(part).strip() != ""
        )
    
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

    def search(self, query: str, limit: int = 10):
        embedding = self.embedding_service.create_embedding(query)
        pgvector = self.embedding_service.to_pgvector(embedding)

        results = self.repository.search_by_vector(pgvector, limit)
        results = self.apply_score_weights(results)

        return {
            "method": "semantic_vector_search",
            "query": query,
            "limit": limit,
            "results": results
        }

    def similar_products(self, product_id: int, limit: int = 10):
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