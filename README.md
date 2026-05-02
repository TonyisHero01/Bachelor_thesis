# Eshop Content Management System

## About

This project is a modular e-commerce system built with Symfony (PHP) and FastAPI (Python).
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

## Architecture (Docker Services)

- bms → http://localhost:8083  
- frontweb → http://localhost:8082  
- python-api → internal service  
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

- Images are built via GitHub Actions and pulled from GHCR
- No local build is required
- `.env` is not committed, use `.env.example`
