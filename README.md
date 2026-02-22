# Eshop Content Management System

## ⚠️ Important Notice

This project no longer supports native (non-Docker) deployment.  
Please use **Docker + Docker Compose** to build and run the system.  
All future development assumes Docker-based environments.

---

## Requirements

- **Docker** + **Docker Compose**
- (Inside containers) **PHP 8.2+**, **Symfony 7.x**
- (Inside container) **Python 3.12+**
- **PostgreSQL 14+**

---

## 🧱 Architecture (Docker Services)

This system runs as multiple services:

- **bms** (Symfony admin backend) → http://localhost:8083  
- **frontweb** (Symfony customer frontend) → http://localhost:8082  
- **python-api** (FastAPI TF-IDF search backend) → http://localhost:8000 (internal in Docker network, optional to expose)
- **db** (PostgreSQL)

### ✅ TF-IDF Vector Update Rule (IMPORTANT)

**All TF-IDF vector updates MUST go through python-api endpoint:**

- `POST /reindex`  ✅ (single source of truth)

BMS / frontweb should **NOT** rebuild vectors locally and should **NOT** run python scripts directly.  
Whenever product/category data changes, Symfony triggers python-api `/reindex`.

---

## Installation Guide

### 🐳 Run with Docker

#### Clone the repository

```sh
git clone -b Symfony_Version --single-branch https://github.com/TonyisHero01/Bachelor_thesis.git
cd Bachelor_thesis
```

#### Start with Docker

Ensure Docker & Docker Compose are installed:

```sh
docker --version
docker compose version
```

Build and start the containers:

```sh
docker compose up --build
```

This will start two containers on ports 8082 and 8083.

### Inside container: install Symfony dependencies

```sh
# Backend:
docker exec -it bms bash
exit

# Frontend:
docker exec -it frontweb bash
exit
```

---

## ⚙️ Setup

### 1. Configure environment variables

Edit `.env` in both `eshop_bms` and `eshop_frontweb`:

```dotenv
DATABASE_URL="postgresql://your_username:your_password@host.docker.internal:5432/eshop_cms?serverVersion=14&charset=utf8"
```

### 2. Configure python-api base url for Symfony
Edit .env in both eshop_bms and eshop_frontweb:
```dotenv
PYTHON_API_BASE_URL="http://python-api:8000"
```

### 3. Set up the super administrator

```sh
docker exec -it bms bash
php bin/console app:create-super-admin
```

### 4. Initialize Shop Info
```sh
docker exec -it bms bash
php bin/console app:init-shopinfo
```

---

## 🌐 Access the Application

- Backend (Admin Panel): http://localhost:8083  
- Frontend (Customer Page): http://localhost:8082

---

## ✅ Tips

- Use `php bin/console debug:router` to inspect all available routes
- Make sure `.htaccess` is present in both `public/` folders for Apache rewrite
- You can use `symfony console` or `docker exec ... php bin/console` for CLI tools

---

Feel free to open issues or pull requests on GitHub if you encounter any problems.
