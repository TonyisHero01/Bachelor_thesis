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

The system includes a dedicated FastAPI-based search service
using a hybrid retrieval architecture:

- TF-IDF / HashingVectorizer semantic retrieval
- Keyword partial-match retrieval
- Hybrid ranking strategy
- Recommendation engine based on:
  - customer search history
  - order history
  - wishlist behavior
- Automatic in-memory index recovery
- Incremental partial reindexing
- PostgreSQL vector persistence

### Benchmark Features

The benchmark system supports:

- vector search vs SQL LIKE comparison
- average response-time evaluation
- cold-start performance testing
- result count comparison
- retrieval accuracy evaluation
- CSV report export
- generated benchmark reports

### Benchmark Report Interface

The project provides a web interface
for generating and viewing benchmark reports
and search evaluation results.

Available locally at:

- http://localhost:8084

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
