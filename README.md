# Eshop Content Management System

## About

This project is a modular e-commerce system built with Symfony (PHP) and FastAPI (Python),
consisting of a customer-facing frontend (Frontweb), an administrative backend (BMS),
and a separate search service for product retrieval.
It uses Docker and GitHub Container Registry (GHCR) for fully remote image builds and local runtime.

---

## Installation Guide

### Requirements

- Docker 28.3.0+
- Docker Compose

Check installation:

```sh
docker --version
docker compose version
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

---

### Pull and start containers

```sh
docker compose pull
docker compose up -d
```

---

## System Architecture (Docker Services)
The system is composed of multiple services running in isolated Docker containers:

- bms → http://localhost:8083  
- frontweb → http://localhost:8082  
- python-api → search service (TF-IDF based, HTTP API) 
- db → PostgreSQL  

---

## Setup

### Create super admin

```sh
docker exec -it eshop_bms bash
php bin/console app:create-super-admin
```

### Initialize shop

```sh
docker exec -it eshop_bms bash
php bin/console app:init-shopinfo
```

---

## Access

- Backend: http://localhost:8083  
- Frontend: http://localhost:8082  

---

## Search Evaluation & Benchmarking

The project includes a dedicated FastAPI-based search service
using a hybrid retrieval architecture.

### Search Architecture

The search engine combines multiple retrieval strategies:

- TF-IDF / HashingVectorizer semantic retrieval
- keyword partial-match retrieval
- hybrid ranking strategy
- cosine similarity scoring
- recommendation engine based on:
  - customer search history
  - product view history
  - wishlist behavior
  - order history
- automatic in-memory index recovery
- incremental partial reindexing
- PostgreSQL vector persistence

The recommendation system additionally supports:

- metadata-aware ranking
- category similarity boosting
- material similarity boosting
- color similarity boosting
- size similarity boosting

---

## Benchmark Features

The benchmark system supports:

- vector search vs SQL LIKE comparison
- average response-time evaluation
- cold-start performance testing
- result count comparison
- retrieval accuracy evaluation
- recommendation evaluation
- recommendation diversity evaluation
- benchmark history generation
- CSV report export
- generated benchmark reports

---

## Benchmark Web Interface

The project provides a dedicated benchmark UI
for generating and viewing benchmark reports
and search evaluation results.

Available locally at:

- http://localhost:8084

The interface displays:

- average vector-search latency
- SQL LIKE latency comparison
- search hit rate
- recommendation hit rate
- category relevance
- recommendation diversity
- query-by-query benchmark details

---

## Synthetic Benchmark Dataset Generation

The project includes a synthetic data generator
used for benchmark and recommendation evaluation.

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

The generated customer behavior is interest-aware.

Example:

- users interested in gaming laptops
  will mostly browse:
  - gaming keyboards
  - gaming mice
  - monitors
  - headphones

This creates more realistic recommendation evaluation
compared to fully random data generation.

---

## Generate Benchmark Dataset

Run inside the benchmark container:

```bash
docker exec -it eshop_benchmark sh
python product_data_generator.py
```

The generator will:

1. clear old benchmark data
2. generate products and customers
3. generate realistic customer behavior
4. create search/order/view history
5. trigger full search reindexing

## Generate Evaluation Report
Open:
    Generate evaluation report

The system evaluates:

Search Metrics

* query hit rate
* average response time
* result availability
* search health

Recommendation Metrics

* SKU hit rate
* category hit rate
* recommendation diversity
* user-interest consistency

### Example Evaluation Metrics
```
Search result hit rate: 100%
Average search response: 13 ms
Recommendation category hit rate: 100%
Recommendation SKU hit rate: 70%
Average category diversity: 1.00
```
Interpretation:

* high hit rate indicates healthy search quality
* low response time indicates efficient retrieval
* high category hit rate means recommendations
    match user interests
* SKU hit rate measures recommendation relevance
* diversity indicates how broad or focused
    recommendations are


---

## API Documentation

Detailed API documentation is available in a separate file:

[API Documentation](./docs/api.md)

The API includes endpoints for:

- Products
- Categories
- Orders
- Customers
- Inventory (colors, sizes, currencies)
- Employees
- Returns
- Shop configuration

All endpoints require authentication using an API token.

---

## Notes

- Images are built via GitHub Actions and published to GHCR
- The system is deployed using prebuilt images (no local build required)
- `.env` is not committed, use `.env.example`
