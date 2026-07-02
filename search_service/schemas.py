from typing import Dict, Any, List
from pydantic import BaseModel, Field, ConfigDict


class SemanticSearchRequest(BaseModel):
    query: str
    limit: int = 10


class SemanticSimilarRequest(BaseModel):
    product_id: int
    limit: int = 10


class SearchRequest(BaseModel):
    query: str = Field(default="", max_length=200)
    limit: int = Field(default=50, ge=1, le=200)


class SearchResult(BaseModel):
    model_config = ConfigDict(extra="allow")

    product_sku: str | None = None
    sku: str | None = None
    similarity: float = 0.0


class SearchResponse(BaseModel):
    results: List[SearchResult]


class ReindexRequest(BaseModel):
    mode: str = Field(default="full", pattern="^(full|check|partial)$")
    reason: str = Field(default="unknown", max_length=64)
    sku: str | None = Field(default=None, max_length=255)
    context: Dict[str, Any] = Field(default_factory=dict)


class ReindexResponse(BaseModel):
    ok: bool
    mode: str
    updated: int | None = None
    product_rows: int | None = None
    distinct_skus: int | None = None
    vector_rows: int | None = None
    reason: str
    context: Dict[str, Any]
    ip: str
    ts: str


class RecommendResult(BaseModel):
    model_config = ConfigDict(extra="allow")

    product_sku: str | None = None
    sku: str | None = None
    similarity: float = 0.0


class RecommendResponse(BaseModel):
    results: List[RecommendResult]


class SessionRecommendRequest(BaseModel):
    viewed_skus: List[str] = Field(default_factory=list)
    cart_skus: List[str] = Field(default_factory=list)
    current_sku: str | None = None
    limit: int = Field(default=10, ge=1, le=100)


class SessionRecommendResponse(BaseModel):
    results: List[RecommendResult]