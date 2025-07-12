FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    python3.11 python3.11-venv python3-pip \
    libpq-dev gcc \
    && ln -sf /usr/bin/python3.11 /usr/local/bin/python \
    && ln -sf /usr/bin/python3.11 /usr/bin/python3

RUN docker-php-ext-install pdo pdo_pgsql

RUN a2enmod rewrite

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

COPY docker/common_python_venv.sh /common_python_venv.sh
COPY docker/frontweb_entrypoint.sh /entrypoint.sh

RUN chmod +x /common_python_venv.sh /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]
CMD ["apache2-foreground"]