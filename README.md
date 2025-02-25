# Eshop Content Management System

## Requirements
- **PHP** 8.2+
- **Symfony** 6+
- **Python** 3.12+
- **PostgreSQL** 14+
- **Composer** 2+
- **pip** (Python package manager)

## Installation Guide

### 1. Clone the repository
```sh
git clone https://github.com/YOUR_GITHUB_USERNAME/eshop-cms.git
cd eshop-cms
```

### 2. Install dependencies
```sh
cd eshop_bms
composer install
cd ../eshop_frontweb
composer install
```
### 3. Set up the database
First, create a PostgreSQL database (you can name it eshop_cms):
```sh
psql -U your_username -c "CREATE DATABASE eshop_cms;"
```

Then, apply the schema using the SQL files in the DB/ folder:
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
psql -U your_username -d eshop_cms -f DB/wishlist.sql
```
### 4. Configure environment variables
Edit .env:
```sh
DATABASE_URL="postgresql://your_username:your_password@127.0.0.1:5432/eshop_cms?serverVersion=14&charset=utf8"
```
### 5. Install Python dependencies
Navigate to the eshop_bms/python_scripts folder and install Python dependencies:
```sh
cd eshop_bms/python_scripts
pip install -r requirements.txt
```
If you don’t have pip, install it using:
```sh
python -m ensurepip --default-pip
```
### 6. Set up the super administrator
```sh
php bin/console app:create-super-admin
```

### 7. Start the Symfony servers
For the backend managment system:
```sh
cd eshop_bms
symfony server:start
```
For the frontend eshop page:
```sh
cd ../eshop_frontweb
symfony server:start
```

### 8. Access the application
	•	Backend (Admin Panel): http://127.0.0.1:8000
	•	Frontend (Customer Page): http://127.0.0.1:8001

