import os
import re
from datetime import datetime
from typing import List, Tuple, Dict, Any

import numpy as np
import psycopg2
from psycopg2.extras import DictCursor
from dotenv import load_dotenv
from fastapi import FastAPI, HTTPException, Request
from pydantic import BaseModel, Field
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity

load_dotenv()

DATABASE_URL = os.getenv("DATABASE_URL")
if not DATABASE_URL:
    raise RuntimeError("DATABASE_URL is null, please check environment variables")

app = FastAPI(title="TF-IDF Search API", version="1.0.2")


def parse_database_url(url: str) -> Tuple[str, str, str, str, str]:
    """Parse DATABASE_URL and return (host, port, dbname, username, password). Supports query string but ignores it."""
    main = url.split("?", 1)[0]
    if "://" not in main:
        raise ValueError("Invalid DATABASE_URL format (missing scheme)")

    after = main.split("://", 1)[1]
    if "@" not in after:
        raise ValueError("Invalid DATABASE_URL format (missing @)")

    creds, hostpart = after.split("@", 1)
    if ":" not in creds:
        raise ValueError("Invalid DATABASE_URL format (missing user:pass)")

    username, password = creds.split(":", 1)

    if "/" not in hostpart:
        raise ValueError("Invalid DATABASE_URL format (missing /dbname)")

    host_port, dbname = hostpart.split("/", 1)

    if ":" in host_port:
        host, port = host_port.split(":", 1)
    else:
        host, port = host_port, "5432"

    return host, port, dbname, username, password


def get_connection():
    """Create a psycopg2 connection from DATABASE_URL."""
    host, port, dbname, username, password = parse_database_url(DATABASE_URL)
    return psycopg2.connect(
        host=host,
        user=username,
        password=password,
        database=dbname,
        port=port,
        cursor_factory=DictCursor,
    )


def ensure_table(cursor) -> None:
    """Ensure product_document_vector table exists (lowercase to avoid PostgreSQL case pitfalls)."""
    cursor.execute(
        """
        CREATE TABLE IF NOT EXISTS product_document_vector (
            sku TEXT PRIMARY KEY,
            document TEXT,
            vector TEXT
        );
        """
    )


def custom_preprocessor(text: str) -> str:
    """Normalize text: lowercase, remove digits and punctuation, collapse whitespace."""
    text = (text or "").lower()
    text = re.sub(r"\d+", "", text)
    text = re.sub(r"[^\w\s]", "", text)
    return text.strip()


def row_to_string(row, weight_factor: int = 20) -> str:
    """Build a weighted document string for a product row (name heavily weighted)."""
    sku = row.get("sku", "") or ""
    name = row.get("name", "") or ""
    category = row.get("category", "") or ""
    description = row.get("description", "") or ""

    if not name and not description:
        return ""

    name = custom_preprocessor(name)
    description = custom_preprocessor(description)
    category = custom_preprocessor(category)

    name_weighted = " ".join([name] * weight_factor) if name else ""
    description_weighted = " ".join([description] * 5) if description else ""

    return f"{sku} {name_weighted} {category} {description_weighted}".strip()


def get_documents(cursor) -> Dict[str, str]:
    """Fetch products and build a sku->document mapping (dedup by sku, keep first occurrence)."""
    cursor.execute(
        """
        SELECT p.*, c.name AS category
        FROM product p
        LEFT JOIN category c ON p.category_id = c.id
        """
    )
    products = cursor.fetchall()

    documents: Dict[str, str] = {}
    seen = set()

    for row in products:
        sku = row.get("sku")
        if not sku:
            continue

        sku = str(sku).strip()
        if sku == "" or sku in seen:
            continue

        doc = row_to_string(row)
        if doc:
            documents[sku] = doc
            seen.add(sku)

    return documents


def update_vectors(cursor, documents: Dict[str, str]) -> int:
    """Full rebuild of product_document_vector. Always clears table first, then inserts fresh vectors."""
    cursor.execute("DELETE FROM product_document_vector;")

    if not documents:
        return 0

    vectorizer = TfidfVectorizer(
        preprocessor=custom_preprocessor,
        token_pattern=r"\b\w+\b",
        lowercase=True,
        ngram_range=(1, 2),
    )

    tfidf_matrix = vectorizer.fit_transform(list(documents.values()))
    dense = tfidf_matrix.toarray()

    for sku, vec in zip(documents.keys(), dense):
        vector_str = ",".join(map(str, vec.tolist()))
        cursor.execute(
            """
            INSERT INTO product_document_vector (sku, document, vector)
            VALUES (%s, %s, %s)
            ON CONFLICT (sku) DO UPDATE
            SET document = EXCLUDED.document, vector = EXCLUDED.vector
            """,
            (sku, documents[sku], vector_str),
        )

    return len(documents)


def fetch_vectors(cursor) -> Tuple[List[str], List[str]]:
    """Load skus and documents from product_document_vector."""
    ensure_table(cursor)
    cursor.execute("SELECT sku, document FROM product_document_vector;")
    rows = cursor.fetchall()

    skus: List[str] = []
    docs: List[str] = []

    for row in rows:
        sku = (row.get("sku") or "").strip()
        doc = (row.get("document") or "").strip()
        if sku == "":
            continue
        skus.append(sku)
        docs.append(doc)

    return skus, docs


def search_tfidf(query: str, skus: List[str], docs: List[str]) -> List[Dict[str, Any]]:
    """Compute cosine similarity scores between query and stored documents using TF-IDF."""
    if not skus or not docs:
        return []

    vectorizer = TfidfVectorizer(
        preprocessor=custom_preprocessor,
        token_pattern=r"\b\w+\b",
        lowercase=True,
        ngram_range=(1, 2),
    )

    all_docs = docs + [query]
    tfidf = vectorizer.fit_transform(all_docs)

    doc_vectors = tfidf[:-1].toarray()
    qv = tfidf[-1].toarray()

    if doc_vectors.shape[0] == 0:
        return []

    sims = cosine_similarity(qv, doc_vectors).flatten()
    idx = np.argsort(sims)[::-1]

    return [{"product_sku": skus[i], "similarity": float(sims[i])} for i in idx]


class SearchRequest(BaseModel):
    """Request body for /search."""
    query: str = Field(default="", max_length=200)
    limit: int = Field(default=50, ge=1, le=200)


class ReindexRequest(BaseModel):
    """Request body for /reindex."""
    mode: str = Field(default="full", pattern="^(full|check)$")
    reason: str = Field(default="unknown", max_length=64)
    context: Dict[str, Any] = Field(default_factory=dict)


def _client_ip(req: Request) -> str:
    """Get best-effort client IP address."""
    xff = req.headers.get("x-forwarded-for")
    if xff:
        return xff.split(",")[0].strip()
    return req.client.host if req.client else "unknown"


@app.get("/health")
def health():
    """Health check endpoint."""
    return {"ok": True}


@app.post("/reindex")
def reindex(req: ReindexRequest, request: Request):
    """Rebuild vectors (full) or return counts (check). This is the only supported update entry."""
    started = datetime.utcnow()
    conn = None
    try:
        conn = get_connection()
        with conn:
            with conn.cursor() as cursor:
                ensure_table(cursor)

                if req.mode == "check":
                    cursor.execute("SELECT COUNT(*) AS cnt FROM product_document_vector;")
                    vec_cnt = int(cursor.fetchone()["cnt"])

                    cursor.execute("SELECT COUNT(*) AS cnt FROM product;")
                    prod_cnt = int(cursor.fetchone()["cnt"])

                    cursor.execute(
                        "SELECT COUNT(DISTINCT sku) AS cnt FROM product WHERE sku IS NOT NULL AND sku <> '';"
                    )
                    sku_cnt = int(cursor.fetchone()["cnt"])

                    return {
                        "ok": True,
                        "mode": "check",
                        "product_rows": prod_cnt,
                        "distinct_skus": sku_cnt,
                        "vector_rows": vec_cnt,
                        "reason": req.reason,
                        "context": req.context,
                        "ip": _client_ip(request),
                        "ts": started.isoformat() + "Z",
                    }

                documents = get_documents(cursor)
                updated = update_vectors(cursor, documents)

                return {
                    "ok": True,
                    "mode": "full",
                    "updated": updated,
                    "reason": req.reason,
                    "context": req.context,
                    "ip": _client_ip(request),
                    "ts": started.isoformat() + "Z",
                }
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))
    finally:
        if conn:
            conn.close()


@app.post("/train")
def train_compat(req: ReindexRequest, request: Request):
    """Backward compatible endpoint: delegates to /reindex."""
    return reindex(req, request)


@app.post("/search")
def search_api(req: SearchRequest):
    """Search endpoint using vectors stored in product_document_vector."""
    q = (req.query or "").strip()
    if not q:
        return {"results": []}

    conn = None
    try:
        conn = get_connection()
        with conn:
            with conn.cursor() as cursor:
                skus, docs = fetch_vectors(cursor)
                results = search_tfidf(q, skus, docs)
                return {"results": results[: req.limit]}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))
    finally:
        if conn:
            conn.close()