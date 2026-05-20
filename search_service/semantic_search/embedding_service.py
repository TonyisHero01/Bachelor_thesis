from sentence_transformers import SentenceTransformer
from config import settings

class EmbeddingService:
    def __init__(self):
        self.model = SentenceTransformer(settings.model_name)

    def create_embedding(self, text: str):
        vector = self.model.encode(
            text,
            normalize_embeddings=True,
            show_progress_bar=False,
        )

        return [float(value) for value in vector]

    def create_embeddings(self, texts: list[str]):
        vectors = self.model.encode(
            texts,
            normalize_embeddings=True,
            show_progress_bar=False,
            batch_size=32,
        )

        return [
            [float(value) for value in vector]
            for vector in vectors
        ]

    def to_pgvector(self, vector):
        return "[" + ",".join(str(value) for value in vector) + "]"