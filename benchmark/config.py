import os

from dataclasses import dataclass
from pathlib import Path

from dotenv import load_dotenv


BASE_DIR = Path(__file__).resolve().parent.parent

load_dotenv(BASE_DIR / ".env")


@dataclass(frozen=True)
class Settings:
    search_url: str
    bms_url: str

    search_api_key: str

    database_url: str

    benchmark_repeat_count: int

    report_dir: Path

    esci_examples_path: str
    esci_query_limit: int
    elasticsearch_url: str

    queries: list[str]


def load_settings() -> Settings:
    database_url = os.getenv("DATABASE_URL")

    if not database_url:
        raise RuntimeError(
            "DATABASE_URL is missing. Please check environment variables."
        )

    report_dir = Path("/app/reports")
    report_dir.mkdir(parents=True, exist_ok=True)

    default_queries = [
        "shirt",
        "dress",
        "shoes",
        "jacket",
        "bag",
        "jeans",
        "cotton",
        "leather",
        "black",
        "white",
    ]

    queries_env = os.getenv("BENCHMARK_QUERIES", "")

    if queries_env.strip():
        queries = [
            item.strip()
            for item in queries_env.split(",")
            if item.strip()
        ]
    else:
        queries = default_queries

    return Settings(
        search_url=os.getenv(
            "SEARCH_URL",
            "http://eshop_search_service:8000",
        ).rstrip("/"),

        bms_url=os.getenv(
            "BMS_URL",
            "http://eshop_bms",
        ).rstrip("/"),

        search_api_key=os.getenv(
            "SEARCH_API_KEY",
            "",
        ),

        database_url=database_url,

        benchmark_repeat_count=int(
            os.getenv("BENCHMARK_REPEAT_COUNT", "5")
        ),

        report_dir=report_dir,

        esci_examples_path=os.getenv(
            "ESCI_EXAMPLES_PATH",
            "/app/esci-data/shopping_queries_dataset/shopping_queries_dataset_examples.parquet",
        ),

        esci_query_limit=int(
            os.getenv("ESCI_QUERY_LIMIT", "100")
        ),

        elasticsearch_url=os.getenv(
            "ELASTICSEARCH_URL",
            "http://elasticsearch:9200",
        ),

        queries=queries,
    )


settings = load_settings()