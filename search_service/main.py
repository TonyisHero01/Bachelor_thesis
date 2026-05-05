import logging
from datetime import datetime

from fastapi import FastAPI, HTTPException, Request

from config import settings
from schemas import (
    SearchRequest,
    SearchResponse,
    ReindexRequest,
    ReindexResponse,
    RecommendResponse,
)
from services.search_service import (
    rebuild_search_index,
    search_products,
    recommend_products,
    get_index_status,
)


logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(name)s: %(message)s",
)

logger = logging.getLogger(__name__)

app = FastAPI(
    title=settings.app_name,
    version=settings.app_version,
)


def client_ip(request: Request) -> str:
    xff = request.headers.get("x-forwarded-for")
    if xff:
        return xff.split(",")[0].strip()

    return request.client.host if request.client else "unknown"


def verify_api_key(request: Request) -> None:
    if not settings.api_key:
        return

    token = request.headers.get("X-API-KEY")

    if token != settings.api_key:
        raise HTTPException(status_code=403, detail="Forbidden")


@app.get("/health")
def health():
    return {
        "ok": True,
        "service": settings.app_name,
        "version": settings.app_version,
    }


@app.post("/search", response_model=SearchResponse)
def search_api(req: SearchRequest):
    query = req.query.strip()

    if not query:
        return {"results": []}

    limit = min(req.limit, settings.max_search_limit)
    results = search_products(query, limit)

    return {"results": results}


@app.post("/reindex", response_model=ReindexResponse)
def reindex(req: ReindexRequest, request: Request):
    verify_api_key(request)

    started = datetime.utcnow()

    try:
        if req.mode == "check":
            status = get_index_status()

            return {
                "ok": True,
                "mode": "check",
                "product_rows": status["product_rows"],
                "distinct_skus": status["distinct_skus"],
                "vector_rows": status["indexed_documents"],
                "reason": req.reason,
                "context": req.context,
                "ip": client_ip(request),
                "ts": started.isoformat() + "Z",
            }

        updated = rebuild_search_index()

        return {
            "ok": True,
            "mode": "full",
            "updated": updated,
            "reason": req.reason,
            "context": req.context,
            "ip": client_ip(request),
            "ts": started.isoformat() + "Z",
        }

    except Exception:
        logger.exception("Reindex failed")
        raise HTTPException(status_code=500, detail="Reindex failed")


@app.post("/train")
def train_compat(req: ReindexRequest, request: Request):
    return reindex(req, request)

@app.get("/recommend/{sku}", response_model=RecommendResponse)
def recommend_api(sku: str, limit: int = 10):
    sku = sku.strip()

    if not sku:
        return {"results": []}

    limit = min(limit, settings.max_search_limit)
    results = recommend_products(sku, limit)

    return {"results": results}