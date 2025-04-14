FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
        git unzip libpq-dev libzip-dev zip \
    && docker-php-ext-install pdo pdo_pgsql zip

RUN a2enmod rewrite

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

CMD ["apache2-foreground"]