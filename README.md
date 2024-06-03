# Eshop Content Management System

## Symfony, Python and MySQL implemented

### Important Python Requirements:
pymysql, sklearn.feature_extraction, sklearn.metrics, numpy, dotenv, os, sys, json.

For detail of requirements please visit [requirements.txt](/eshop_cms/python_scripts/requirements.txt)

### Get Started

#### Database Configuration:
Configure your database in file [.env](/eshop_cms/.env) on line:
``` 
DATABASE_URL="mysql://<username>:<password>@<host>:<port>/<db_name>?serverVersion=5.7"
```
You can use also other database, see: [Symfony Documentation](https://symfony.com/doc/current/doctrine.html)   
#### Run Symfony server:
After downloading run command in repository [eshop_cms](/eshop_cms/): 
``` 
$ symfony server:start
```

Then visit main page: [http://127.0.0.1:8000/product_list](http://127.0.0.1:8000/product_list)