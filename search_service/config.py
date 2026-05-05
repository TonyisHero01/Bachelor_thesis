import os
from dataclasses import dataclass
from dotenv import load_dotenv

load_dotenv()

@dataclass(frozen=True)
class Settings:
    database_url: str
    api_key: str | None
    app_name: str = "TF-IDF Search API"
    app_version: str = "2.0.0"
    max_search_limit: int = 200
    default_search_limit: int = 50


def load_settings() -> Settings:
    database_url = os.getenv("DATABASE_URL")

    if not database_url:
        raise RuntimeError("DATABASE_URL is missing. Please check environment variables.")

    max_search_limit = int(os.getenv("MAX_SEARCH_LIMIT", "200"))
    default_search_limit = int(os.getenv("DEFAULT_SEARCH_LIMIT", "50"))

    return Settings(
        database_url=database_url,
        api_key=os.getenv("SEARCH_API_KEY"),
        max_search_limit=max_search_limit,
        default_search_limit=default_search_limit,
    )


settings = load_settings()