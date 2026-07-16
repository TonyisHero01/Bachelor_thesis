# Eshop Content Management System

## About

This project is a modular e-commerce system built with Symfony, FastAPI, PostgreSQL, Elasticsearch, and Docker.

The system consists of:

- Frontweb: customer-facing e-shop frontend
- BMS: administrative backend system
- Search Service: FastAPI-based product search and recommendation service
- Benchmark Service: evaluation and reporting module for search and recommendation quality
- PostgreSQL database with pgvector support
- Elasticsearch service for BM25-based search

Docker images are built remotely using GitHub Actions and published to GitHub Container Registry (GHCR).
The local environment runs from prebuilt images, so no local build is required by default.

---

## Installation Guide

### Requirements

- Docker 28.3.0+
- Docker Compose
- Git

Check installation:

```sh
docker --version
docker compose version
git --version
```

---

## Run with Docker

### Clone the repository

```sh
git clone https://github.com/TonyisHero01/Bachelor_thesis.git
cd Bachelor_thesis
```

---

### Setup environment

Create a local `.env` file:

```sh
cp .env.example .env
```

Edit values if needed.

Important environment values include:

```env
POSTGRES_DB=app
POSTGRES_USER=user
POSTGRES_PASSWORD=password

DATABASE_URL=postgresql://user:password@db:5432/app

SEARCH_SERVICE_BASE_URL=http://search-service:8000
SEARCH_API_KEY=your-api-key
```

Note: `SEARCH_SERVICE_BASE_URL` is used by Symfony containers. Therefore it should use the Docker service name:

```env
SEARCH_SERVICE_BASE_URL=http://search-service:8000
```

Do not use `http://localhost:8081` inside containers. That address is only for accessing the search service from the host machine.

---

### Pull and start containers

```sh
docker compose pull
docker compose up -d
```


Check running containers:

```sh
docker compose ps
```

---

## System Architecture

The system is composed of multiple Docker services:

| Service | Description | Local URL |
|---|---|---|
| `bms` | Symfony administrative backend | http://localhost:8083 |
| `frontweb` | Symfony customer-facing frontend | http://localhost:8082 |
| `search-service` | FastAPI search and recommendation service | http://localhost:8081 |
| `benchmark` | Benchmark and evaluation web interface | http://localhost:8084 |
| `db` | PostgreSQL database with pgvector support | internal |
| `elasticsearch` | Elasticsearch service for BM25 search | http://localhost:9200 |

The backend and frontend use the same PostgreSQL database.
The search service communicates with PostgreSQL and Elasticsearch.

---

## Initial Setup

### Create super admin

```sh
docker exec -it eshop_bms bash
php bin/console app:create-super-admin
```

### Initialize shop information

```sh
docker exec -it eshop_bms bash
php bin/console app:init-shopinfo
```

---

## Access

- Backend: http://localhost:8083
- Frontend: http://localhost:8082
- Benchmark UI: http://localhost:8084
- Search Service: http://localhost:8081
- Elasticsearch: http://localhost:9200

---

## Search and Recommendation System

The project includes a dedicated FastAPI-based search service.

The search service exposes fixed public endpoints:

- `POST /search`
- `GET /recommend/{sku}`
- `POST /recommend/session`

The frontend does not choose the search algorithm directly.
Instead, the active algorithm is selected by the backend configuration stored in the database table:

```sql
search_relevance_config.search_method
```

Supported search methods:

- `tfidf`
- `semantic_vector`
- `elasticsearch_bm25`

The active search method is used consistently for both:

- product search
- product recommendation

---

## Search Methods

### TF-IDF Search

The TF-IDF method uses document vectors generated from product data.

It supports:

- product name weighting
- description weighting
- category weighting
- material weighting
- color weighting
- size weighting
- attribute weighting
- cosine similarity ranking
- persistent vector storage in PostgreSQL

---

### Semantic Vector Search

The semantic vector method uses sentence embeddings for meaning-based search.

It is useful for natural-language queries where the user describes intent instead of exact product keywords.

Example:

```text
Query: computer for playing games
Expected category: Laptops
```

```text
Query: device for typing
Expected category: Keyboards
```

```text
Query: warm clothes for winter
Expected category: Jackets
```

---

### Elasticsearch BM25 Search

The Elasticsearch method uses BM25-based full-text retrieval.

It supports:

- keyword search
- weighted fields
- product name boosting
- category boosting
- description search
- material, color, and size fields
- Elasticsearch index-based retrieval

---

## Indexing Behavior

The search service uses lazy index initialization.

The `/ready` endpoint only checks whether the FastAPI service is running.

Indexes are created automatically on first real usage.

For example:

```text
First call to /search
↓
Search service reads active search method
↓
Search service checks whether the required index exists
↓
If missing, the index is automatically created
↓
Search results are returned
```

The same behavior applies to:

- `/search`
- `/recommend/{sku}`
- `/recommend/session`
- `/semantic/search`
- `/semantic/similar`
- `/elastic/search`

This avoids Docker startup deadlocks while still ensuring that the correct search index is available when needed.

---

## Manual Reindexing

Manual reindexing is also supported.

### TF-IDF full reindex

```sh
curl -X POST http://localhost:8081/reindex \
  -H "Content-Type: application/json" \
  -H "X-API-KEY: your-api-key" \
  -d '{"mode":"full","reason":"manual"}'
```

Check TF-IDF reindex status:

```sh
curl http://localhost:8081/reindex/status \
  -H "X-API-KEY: your-api-key"
```

### Semantic vector reindex

```sh
curl -X POST http://localhost:8081/semantic/reindex
```

### Elasticsearch reindex

```sh
curl -X POST http://localhost:8081/elastic/reindex
```

---

## Recommendation System

The recommendation system supports product-based and session-based recommendations.

Recommendation signals include:

- product similarity
- category similarity
- material similarity
- color similarity
- size similarity
- wishlist behavior
- order history
- search history
- product view history
- session activity

The recommendation service uses the same active algorithm as the search service.

For example, if the active method is:

```text
semantic_vector
```

then both product search and product recommendation use semantic vector logic.

---

## Benchmark Features

The benchmark system supports:

- search response-time evaluation
- search result availability
- query hit-rate evaluation
- recommendation evaluation
- SKU hit-rate evaluation
- category hit-rate evaluation
- recommendation diversity evaluation
- user-interest consistency evaluation
- benchmark history generation
- CSV report export
- generated benchmark reports

---

## Benchmark Web Interface

The project provides a dedicated benchmark UI for generating and viewing benchmark reports.

Available locally at:

```text
http://localhost:8084
```

The interface displays:

- average search latency
- search hit rate
- recommendation hit rate
- category relevance
- recommendation diversity
- query-by-query benchmark details

---

## Synthetic Benchmark Dataset Generation

The project includes a synthetic data generator used for benchmark and recommendation evaluation.

The generator creates:

- product catalog
- categories
- colors
- sizes
- customers
- wishlists
- search history
- product-view history
- order history
- persona-based customer behavior

The generated customer behavior is interest-aware.

Example:

- users interested in gaming laptops will mostly browse:
  - gaming keyboards
  - gaming mice
  - gaming monitors
  - gaming headsets

This creates more realistic recommendation evaluation compared to fully random data generation.

---

## Generate Benchmark Dataset

The e-shop must be initialized before running the benchmark data generator. At minimum, complete the initial BMS setup first:

```sh
docker exec -it eshop_bms bash
php bin/console app:create-super-admin
php bin/console app:init-shopinfo
exit
```

The Amazon ESCI dataset must also be available inside the Benchmark container. The optional download procedure is described in the final section of this README.

After both conditions are satisfied, run the generator from the project root:

```sh
docker compose exec benchmark \
  python /app/product_data_generator.py
```

Running `product_data_generator.py` before the e-shop initialization is complete can fail because required database records and application configuration are not yet available.

The generator will:

1. clear old benchmark data
2. generate categories, colors, sizes, products, and customers
3. generate realistic customer behavior
4. create search, order, wishlist, and product-view history
5. trigger full search reindexing

---

## Generate Evaluation Report

Open the benchmark web interface:

```text
http://localhost:8084
```

Then click:

```text
Generate evaluation report
```

The system evaluates both search and recommendation quality.

### Search Metrics

- query hit rate
- average response time
- result availability
- search health

### Recommendation Metrics

- SKU hit rate
- category hit rate
- recommendation diversity
- user-interest consistency

### Example Evaluation Metrics

```text
Search result hit rate: 100%
Average search response: 13 ms
Recommendation category hit rate: 100%
Recommendation SKU hit rate: 70%
Average category diversity: 1.00
```

Interpretation:

- high hit rate indicates healthy search quality
- low response time indicates efficient retrieval
- high category hit rate means recommendations match user interests
- SKU hit rate measures recommendation relevance
- diversity indicates how broad or focused recommendations are

---

## API Documentation

Detailed API documentation is available in a separate file:

[API Documentation](./docs/api.md)

The API includes endpoints for:

- Products
- Categories
- Orders
- Customers
- Inventory
- Colors
- Sizes
- Currencies
- Employees
- Returns
- Shop configuration

All protected API endpoints require authentication using an API token.

---

## Notes

- Images are built via GitHub Actions and published to GHCR.
- The system is deployed using prebuilt images.
- No local build is required by default.
- `.env` is not committed.
- Use `.env.example` as the base environment file.
- Search indexes are initialized lazily on first use.
---

## Optional: Download the Amazon ESCI Dataset

The Amazon ESCI dataset is required only when benchmark data or evaluation data must be generated. It is not committed to this repository because the required Parquet files are larger than 1 GB.

The dataset is downloaded directly from the official Amazon Science repository:

```text
https://github.com/amazon-science/esci-data.git
```

### Install Git LFS on Ubuntu or WSL

```sh
sudo apt update
sudo apt install -y git-lfs
git lfs install
```

Verify the installation:

```sh
git lfs version
```

### Clone the dataset if needed

Run the following commands from the project root only if the ESCI dataset is needed:

```sh
cd benchmark
git clone https://github.com/amazon-science/esci-data.git
cd ..
```

Git LFS downloads the large Parquet files during cloning. The expected host paths are:

```text
benchmark/esci-data/shopping_queries_dataset/shopping_queries_dataset_examples.parquet
benchmark/esci-data/shopping_queries_dataset/shopping_queries_dataset_products.parquet
benchmark/esci-data/shopping_queries_dataset/shopping_queries_dataset_sources.csv
```

Verify the downloaded files:

```sh
ls -lh benchmark/esci-data/shopping_queries_dataset
```

The directory should contain approximately:

```text
49M  shopping_queries_dataset_examples.parquet
1.1G shopping_queries_dataset_products.parquet
1.7M shopping_queries_dataset_sources.csv
```

If the repository was cloned before Git LFS was installed, download the actual LFS files with:

```sh
cd benchmark/esci-data
git lfs pull
cd ../..
```

Docker Compose mounts the dataset read-only into the Benchmark container:

```text
Host:      ./benchmark/esci-data
Container: /app/esci-data
```

After downloading the dataset, recreate the Benchmark container. A normal `docker compose up -d` may keep the existing container without applying the new bind-mount contents:

```sh
docker compose up -d --force-recreate benchmark
```

Verify that the files are visible inside the container:

```sh
docker compose exec benchmark \
  ls -lh /app/esci-data/shopping_queries_dataset
```

Only after the e-shop initialization is complete and this verification succeeds should the benchmark dataset generator be executed:

```sh
docker compose exec benchmark \
  python /app/product_data_generator.py
```