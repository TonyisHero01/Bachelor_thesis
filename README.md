# Eshop Content Management System

## ⚠️ Important Notice

This project no longer supports native (non-Docker) deployment.  
Please use **Docker + Docker Compose** to build and run the system.  
All future development assumes Docker-based environments.

---

## Requirements

- **Docker** + **Docker Compose**
- (Inside containers) **PHP 8.4+**, **Symfony 7.4.x**
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

#### Edit your .github/workflows/docker-build-and-push.yml!
```yml
IMAGE_NAMESPACE: ghcr.io/<your-github-username>
```

#### Start with Docker

Ensure Docker & Docker Compose are installed:

```sh
docker --version
docker compose version
```

---

#### Running the application

This project supports multiple run modes depending on your machine resources.

##### 🟢 Recommended for 8 GB RAM (lightweight mode)

Starts only the core services (`db` + `bms`) to reduce memory usage.

```sh
docker compose up -d
```

Access:
- BMS: http://localhost:8083

---

##### 🟡 Optional services

If you need additional features, you can enable services selectively:

**Start Python API:**
```sh
docker compose --profile python up -d
```

**Start Frontend (frontweb):**
```sh
docker compose --profile frontweb up -d
```

---

##### 🔵 Full mode (recommended for 16 GB RAM or higher)

Starts all services (`db`, `bms`, `python-api`, `frontweb`):

```sh
docker compose --profile full up -d
```

Access:
- Frontend: http://localhost:8082  
- BMS: http://localhost:8083

---

#### Notes

- On machines with limited memory (e.g. 8 GB RAM), running all services simultaneously may lead to reduced performance.
- It is recommended to start only the services needed for your current task.
- The use of Docker profiles allows flexible resource management and improves usability on lower-spec devices.

---

## ⚙️ Setup

### 1. Configure environment variables

Edit `.env`:

```dotenv
DATABASE_URL="postgresql://your_username:your_password@host.docker.internal:5432/eshop_cms?serverVersion=14&charset=utf8"
```

### 2. Configure python-api base url for Symfony
Edit .env:
```dotenv
PYTHON_API_BASE_URL="http://python-api:8000"
```

### 3. Set up the super administrator

```sh
docker exec -it eshop_bms bash
php bin/console app:create-super-admin
```

### 4. Initialize Shop Info
```sh
docker exec -it eshop_bms bash
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
