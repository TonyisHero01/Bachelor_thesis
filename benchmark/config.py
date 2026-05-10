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

    queries: list[str]


def load_settings() -> Settings:
    database_url = os.getenv("DATABASE_URL")

    if not database_url:
        raise RuntimeError(
            "DATABASE_URL is missing. Please check environment variables."
        )

    report_dir = Path("/app/reports")
    report_dir.mkdir(parents=True, exist_ok=True)

    return Settings(
        search_url=os.getenv(
            "SEARCH_URL",
            "http://search-service:8000",
        ).rstrip("/"),

        bms_url=os.getenv(
            "BMS_URL",
            "http://bms",
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

        queries=[
            "laptop",
            "smartphone",
            "keyboard",
            "mouse",
            "headphones",
            "shirt",
            "jacket",
            "cotton",
            "black",
            "white",
        ],
    )


settings = load_settings()