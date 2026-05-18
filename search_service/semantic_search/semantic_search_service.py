from semantic_search.embedding_service import EmbeddingService
from semantic_search.semantic_vector_repository import SemanticVectorRepository


class SemanticSearchService:
    def __init__(self):
        self.repository = SemanticVectorRepository()
        self.embedding_service = EmbeddingService()

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

    def reindex(self):
        products = self.repository.get_products_for_indexing()

        indexed_count = 0

        for product in products:
            product_id = product["id"] if isinstance(product, dict) else product[0]
            document = self.build_product_document(product)

            if document == "":
                continue

            embedding = self.embedding_service.create_embedding(document)
            pgvector = self.embedding_service.to_pgvector(embedding)

            self.repository.save_embedding(product_id, pgvector)

            indexed_count += 1

        return {
            "status": "ok",
            "indexed_products": indexed_count
        }

    def search(self, query: str, limit: int = 10):
        embedding = self.embedding_service.create_embedding(query)
        pgvector = self.embedding_service.to_pgvector(embedding)

        results = self.repository.search_by_vector(pgvector, limit)

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