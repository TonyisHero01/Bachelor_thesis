from sentence_transformers import SentenceTransformer
from config import settings


class EmbeddingService:
    def __init__(self):
        self.model = SentenceTransformer(settings.model_name)

    def create_embedding(self, text: str):
        vector = self.model.encode(
            text,
            normalize_embeddings=True
        )

        return [float(value) for value in vector]

    def to_pgvector(self, vector):
        return "[" + ",".join(str(value) for value in vector) + "]"