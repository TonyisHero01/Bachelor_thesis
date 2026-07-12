import json
import logging
from typing import Any

from sentence_transformers import SentenceTransformer

from config import settings


logger = logging.getLogger(__name__)


DEFAULT_EMBEDDING_SETTINGS = {
    "normalize_embeddings": True,
    "batch_size": 32,
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


def parse_algorithm_settings(
    config: dict | None,
) -> dict:
    if not isinstance(config, dict):
        return {}

    value = config.get(
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
                "[CONFIG][SEMANTIC] invalid "
                "algorithm_settings JSON"
            )

    return {}


class EmbeddingService:
    def __init__(
        self,
        config: dict | None = None,
    ):
        self.config: dict = {}

        # Model name remains an application/deployment setting.
        self.model_name = settings.model_name

        self.normalize_embeddings = (
            DEFAULT_EMBEDDING_SETTINGS[
                "normalize_embeddings"
            ]
        )

        self.batch_size = (
            DEFAULT_EMBEDDING_SETTINGS[
                "batch_size"
            ]
        )

        logger.info(
            "[SEMANTIC][EMBEDDING] loading model=%s",
            self.model_name,
        )

        self.model = SentenceTransformer(
            self.model_name
        )

        dimension = (
            self.model
            .get_sentence_embedding_dimension()
        )

        if dimension is None:
            raise RuntimeError(
                "The embedding model did not provide "
                "an embedding dimension."
            )

        self.embedding_dimension = int(
            dimension
        )

        self.reload_config(
            config or {}
        )

        logger.info(
            "[SEMANTIC][EMBEDDING] model loaded "
            "model=%s dimension=%s "
            "normalize_embeddings=%s "
            "batch_size=%s",
            self.model_name,
            self.embedding_dimension,
            self.normalize_embeddings,
            self.batch_size,
        )

    def reload_config(
        self,
        config: dict | None,
    ) -> None:
        self.config = (
            config
            if isinstance(config, dict)
            else {}
        )

        algorithm_settings = (
            parse_algorithm_settings(
                self.config
            )
        )

        embedding_settings = (
            algorithm_settings.get(
                "embedding",
                {},
            )
        )

        if not isinstance(
            embedding_settings,
            dict,
        ):
            embedding_settings = {}

        self.normalize_embeddings = (
            parse_boolean(
                embedding_settings.get(
                    "normalize_embeddings"
                ),
                DEFAULT_EMBEDDING_SETTINGS[
                    "normalize_embeddings"
                ],
            )
        )

        self.batch_size = (
            parse_positive_int(
                embedding_settings.get(
                    "batch_size"
                ),
                DEFAULT_EMBEDDING_SETTINGS[
                    "batch_size"
                ],
            )
        )

        logger.info(
            "[CONFIG][SEMANTIC] embedding "
            "configuration reloaded "
            "normalize_embeddings=%s "
            "batch_size=%s",
            self.normalize_embeddings,
            self.batch_size,
        )

    def get_dimension(self) -> int:
        return self.embedding_dimension

    def create_embedding(
        self,
        text: str,
    ) -> list[float]:
        normalized_text = str(
            text or ""
        ).strip()

        if not normalized_text:
            raise ValueError(
                "Embedding text cannot be empty."
            )

        vector = self.model.encode(
            normalized_text,
            normalize_embeddings=(
                self.normalize_embeddings
            ),
            show_progress_bar=False,
        )

        return [
            float(value)
            for value in vector
        ]

    def create_embeddings(
        self,
        texts: list[str],
    ) -> list[list[float]]:
        normalized_texts = [
            str(text or "").strip()
            for text in texts
        ]

        normalized_texts = [
            text
            for text in normalized_texts
            if text
        ]

        if not normalized_texts:
            return []

        vectors = self.model.encode(
            normalized_texts,
            normalize_embeddings=(
                self.normalize_embeddings
            ),
            show_progress_bar=False,
            batch_size=self.batch_size,
        )

        return [
            [
                float(value)
                for value in vector
            ]
            for vector in vectors
        ]

    def to_pgvector(
        self,
        vector,
    ) -> str:
        return (
            "["
            + ",".join(
                str(float(value))
                for value in vector
            )
            + "]"
        )