FROM php:8.2-apache-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    python3.11 python3.11-venv python3-pip \
    libpq-dev gcc \
 && ln -sf /usr/bin/python3.11 /usr/local/bin/python \
 && ln -sf /usr/bin/python3.11 /usr/bin/python3 \
 && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo pdo_pgsql
RUN a2enmod rewrite

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .

COPY docker/common_python_venv.sh /common_python_venv.sh
COPY docker/frontweb_entrypoint.sh /usr/local/bin/start-frontweb.sh

RUN chmod +x /common_python_venv.sh /usr/local/bin/start-frontweb.sh

ENTRYPOINT ["/usr/local/bin/start-frontweb.sh"]
CMD ["apache2-foreground"]