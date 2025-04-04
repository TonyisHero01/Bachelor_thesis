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
Python is used in this project to provide full-text search functionality in the product catalog.     
Navigate to the eshop_bms/python_scripts folder and 
#### Create Virtual Enviroment:
```sh
cd eshop_bms/python_scripts
python3 -m venv venv
source venv/bin/activate
```

```sh
cd ../../eshop_frontweb/python_scripts
python3 -m venv venv
source venv/bin/activate
```
#### Install Python dependencies:
```sh
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
#### Important: If you are using the DB/test_data_insertion.sql file to load test data, please note the following:

1. **Employee Test Data**: The file does not include employee test data because the passwords are hashed. You must first create a super admin (using the app:create-super-admin command) and then manually create employees through the BMS website.

2. **Customer Test Data**: Similar to the employee data, the customer test data also uses hashed passwords. You cannot insert customer data directly without generating the hashed passwords first. You must create customers manually or follow the same process as for employees (create a super admin and then add customers through the BMS website).

3. **Orders and Order Items**: Since customer data is required to insert orders and order items, and customer data cannot be inserted without valid customer records (due to password hashing), you must first create customers and then proceed with inserting orders and order items. These records are dependent on existing customer data.

4. Additionally, after adding products using the test data, you need to manually add images to the `eshop_bms/public/images` folder. Ensure that the image filenames match those specified in the test data, otherwise, products will display incorrectly on the frontweb store page.

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

