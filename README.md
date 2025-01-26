# Eshop Content Management System

## Symfony, Python and MySQL implemented

### Important Python Requirements:
pymysql, sklearn.feature_extraction, sklearn.metrics, numpy, dotenv, os, sys, json.

For detail of requirements please visit [requirements.txt](/eshop_bms/python_scripts/requirements.txt)

### Get Started

#### Database Configuration:
Configure your database in files [eshop_bms/.env](/eshop_bms/.env) and [eshop_frontweb/.env](/eshop_frontweb/.env) on line:
``` 
DATABASE_URL="postgresql://app:!ChangeMe!@127.0.0.1:5432/app?serverVersion=16&charset=utf8"
```
You cannot use other database because of python script.  
#### Before First Running:
Please run command in eshop_bms and eshop_frontweb repository:
``` 
$ composer install
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

Then visit main page: [http://127.0.0.1:8000/product_list](http://127.0.0.1:8000/product_list)
For visiting employee registration page: [http://127.0.0.1:8000/register_employee](http://127.0.0.1:8000/register_employee)

#### Run Symfony Customer server:
After downloading run command in repository [eshop_frontweb](/eshop_frontweb/): 
``` 
$ symfony server:start
```
Then visit customer page: [http://127.0.0.1:8001](http://127.0.0.1:8001)