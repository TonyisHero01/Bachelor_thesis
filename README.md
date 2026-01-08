
# Eshop Content Management System

## ⚠️ Important Notice

This project no longer supports native (non-Docker) deployment.  
Please use **Docker + Docker Compose** to build and run the system.  
All future development assumes Docker-based environments.

## Requirements

- **PHP** 8.2+
- **Symfony** 7.0.10
- **Python** 3.12+
- **PostgreSQL** 14+
- **Composer** 2+
- **pip** (Python package manager)
- **Docker** + **Docker Compose**

---

## Installation Guide

---

### 🐳 Run with Docker

#### Clone the repository

```sh
git clone https://github.com/TonyisHero01/Bachelor_thesis.git
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
docker exec -it bachelor_thesis-symfony_version-apache-bms-1 bash
exit

# Frontend:
docker exec -it bachelor_thesis-symfony_version-apache-frontweb-1 bash
exit
```

---

## ⚙️ Setup

### 1. Configure environment variables

Edit `.env` in both `eshop_bms` and `eshop_frontweb`:

```dotenv
DATABASE_URL="postgresql://your_username:your_password@host.docker.internal:5432/eshop_cms?serverVersion=14&charset=utf8"
```

### 2. Set up the super administrator

```sh
docker exec -it bachelor_thesis-symfony_version-apache-bms-1 bash
php bin/console app:create-super-admin
```

### 3. Initialize Shop Info
```sh
docker exec -it bachelor_thesis-symfony_version-apache-bms-1 bash
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
