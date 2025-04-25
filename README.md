
# Eshop Content Management System

## Requirements

- **PHP** 8.2+
- **Symfony** 7.0.10
- **Python** 3.12+
- **PostgreSQL** 14+
- **Composer** 2+
- **pip** (Python package manager)
- **Docker** + **Docker Compose** (optional, if running via containers)

---

## Installation Guide

---

## 🛠️ Option 1: Run Symfony Natively (No Docker)

### Start the Symfony servers

```sh
# Backend
cd eshop_bms
symfony server:start

# Frontend
cd ../eshop_frontweb
symfony server:start
```

---

## 🐳 Option 2: Run with Docker

### Start with Docker

Ensure Docker & Docker Compose are installed:

```sh
docker --version
docker compose version
```

Build and start the containers:

```sh
docker compose up --build
```

This will start two Apache containers on ports 8082 and 8083.

### Inside container: install Symfony dependencies

```sh
# Backend:
docker exec -it bachelor_thesis-symfony_version-apache-bms-1 bash
composer install
php bin/console cache:clear
exit

# Frontend:
docker exec -it bachelor_thesis-symfony_version-apache-frontweb-1 bash
composer install
php bin/console cache:clear
exit
```

---

## ⚙️ Common Setup (For Both Docker & Native)

### 1. Clone the repository

```sh
git clone https://github.com/YOUR_GITHUB_USERNAME/eshop-cms.git
cd eshop-cms
```

### 2. Install PHP dependencies

```sh
cd eshop_bms
composer install
cd ../eshop_frontweb
composer install
```

### 3. Set up the database

Create the database:

```sh
psql -U your_username -c "CREATE DATABASE eshop_cms;"
```

Apply schema:

```sh
psql -U your_username -d eshop_cms -f DB/category.sql
psql -U your_username -d eshop_cms -f DB/color.sql
psql -U your_username -d eshop_cms -f DB/currency.sql
psql -U your_username -d eshop_cms -f DB/customer.sql
psql -U your_username -d eshop_cms -f DB/employee.sql
psql -U your_username -d eshop_cms -f DB/order.sql
psql -U your_username -d eshop_cms -f DB/order_items.sql
psql -U your_username -d eshop_cms -f DB/product.sql
psql -U your_username -d eshop_cms -f DB/shopInfo.sql
```

### 4. Configure environment variables

Edit `.env` in both `eshop_bms` and `eshop_frontweb`:

```dotenv
DATABASE_URL="postgresql://your_username:your_password@127.0.0.1:5432/eshop_cms?serverVersion=14&charset=utf8"
```

Or if using Docker:

```dotenv
DATABASE_URL="postgresql://your_username:your_password@host.docker.internal:5432/eshop_cms?serverVersion=14&charset=utf8"
```

### 5. Install Python dependencies

Navigate to each Python script folder and set up virtual environments:

```sh
# Backend
cd eshop_bms/python_scripts
python3 -m venv venv
source venv/bin/activate
pip install -r requirements.txt

# Frontend
cd ../../eshop_frontweb/python_scripts
python3 -m venv venv
source venv/bin/activate
pip install -r requirements.txt
```

If pip is missing:

```sh
python -m ensurepip --default-pip
```

### 6. Set up the super administrator

```sh
cd eshop_bms
php bin/console app:create-super-admin
```

#### ⚠️ Important Notes on Test Data:

If you're loading `DB/test_data_insertion.sql`, please note:

1. **Employees**: You must first create a super admin and add employees via the BMS web UI.
2. **Customers**: Same reason (password hashing), create them via UI.
3. **Orders**: Require valid customers first.
4. **Product Images**: Add image files manually to `eshop_bms/public/images/` and match filenames used in test data.

---

## 🌐 Access the Application

### Native mode

- Backend (Admin Panel): http://127.0.0.1:8000  
- Frontend (Customer Page): http://127.0.0.1:8001

### Docker mode

- Backend (Admin Panel): http://localhost:8083  
- Frontend (Customer Page): http://localhost:8082

---

## ✅ Tips

- Use `php bin/console debug:router` to inspect all available routes
- Make sure `.htaccess` is present in both `public/` folders for Apache rewrite
- You can use `symfony console` or `docker exec ... php bin/console` for CLI tools

---

Feel free to open issues or pull requests on GitHub if you encounter any problems.
