# Eshop Content Management System

## Symfony, Python and MySQL implemented

### Important Python Requirements:
pymysql, sklearn.feature_extraction, sklearn.metrics, numpy, dotenv, os, sys, json.

For detail of requirements please visit [requirements.txt](/eshop_cms/python_scripts/requirements.txt)

### Get Started

#### Database Configuration:
Configure your database in file [.env](/eshop_cms/.env) on line:
``` 
DATABASE_URL="postgresql://app:!ChangeMe!@127.0.0.1:5432/app?serverVersion=16&charset=utf8"
```
You cannot use other database because of python script.  
#### Before First Running:
Please run command in eshop_bms repository:

``` 
$ sudo apt install php8.2-xml
``` 
``` 
sudo apt install php8.2-pgsql
``` 
``` 
$ composer install
```
In PostgreSQL:
Create database eshop_bms and assign it to the user:
```
CREATE DATABASE your_dbname;
GRANT ALL PRIVILEGES ON DATABASE your_dbname TO your_username;
```
Create tables using schema in DB repository   
If the user needs permissions for all tables, you can run:
```
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO your_user;
```
If the user needs to grant sequence privileges for all sequences:
```
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO your_user;
```
Then run the following command to set super administrator
``` 
$ php bin/console app:create-super-admin
``` 
#### Run Symfony server:
After downloading run command in repository [eshop_bms](/eshop_bms/): 
``` 
$ symfony server:start
```

#### Run front eshop website:
After starting server run command in repository [eshop_frontweb](/eshop_frontweb/): 
``` 
$ symfony server:start --port=8001
```

Then visit main page for employee: [http://127.0.0.1:8000/product_list](http://127.0.0.1:8000/product_list)
For visiting employee registration page for employee: [http://127.0.0.1:8000/register_employee](http://127.0.0.1:8000/register_employee)

For visiting front eshop website: [http://127.0.0.1:8001](http://127.0.0.1:8001)
