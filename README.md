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
Please run command in eshop_cms repository:
``` 
$ composer install
``` 
Then run the following command to set super administrator
``` 
$ php bin/console app:create-super-admin
``` 
#### Run Symfony server:
After downloading run command in repository [eshop_cms](/eshop_cms/): 
``` 
$ symfony server:start
```

Then visit main page: [http://127.0.0.1:8000/product_list](http://127.0.0.1:8000/product_list)
For visiting employee registration page: [http://127.0.0.1:8000/register_employee](http://127.0.0.1:8000/register_employee)