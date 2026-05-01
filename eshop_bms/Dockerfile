FROM php:8.4-apache

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        ca-certificates \
        gnupg \
        debian-archive-keyring; \
    rm -rf /var/lib/apt/lists/* /var/cache/apt/archives/*

RUN set -eux; \
    for f in /etc/apt/sources.list /etc/apt/sources.list.d/*.list /etc/apt/sources.list.d/*.sources; do \
      [ -f "$f" ] || continue; \
      sed -i 's|http://deb.debian.org|https://deb.debian.org|g' "$f" || true; \
      sed -i 's|http://security.debian.org|https://security.debian.org|g' "$f" || true; \
    done

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        git \
        unzip \
        libzip-dev \
        libpq-dev \
        gcc \
        make \
        pkg-config; \
    docker-php-ext-install pdo_pgsql zip; \
    a2enmod rewrite; \
    rm -rf /var/lib/apt/lists/*; \
    apt-get clean; \
    rm -rf /var/cache/apt/archives/* /tmp/* /var/tmp/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY ./eshop_bms/composer.json ./composer.json
COPY ./eshop_bms/composer.lock ./composer.lock

RUN composer install --no-interaction --prefer-dist --no-progress --no-scripts

COPY ./eshop_bms/ /var/www/html/

RUN cat > /etc/apache2/sites-enabled/000-default.conf <<'EOF'
<VirtualHost *:80>
    ServerAdmin webmaster@localhost
    DocumentRoot /var/www/html/public

    <Directory /var/www/html/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
EOF

RUN { \
    echo "upload_max_filesize=20M"; \
    echo "post_max_size=25M"; \
    echo "max_file_uploads=20"; \
} > /usr/local/etc/php/conf.d/uploads.ini

RUN composer dump-autoload --optimize

RUN mkdir -p /var/www/html/var /var/www/html/public/images \
    && chown -R www-data:www-data /var/www/html/var /var/www/html/public/images \
    && chmod -R 775 /var/www/html/var /var/www/html/public/images

COPY docker/bms_entrypoint.sh /usr/local/bin/start-bms.sh

RUN sed -i 's/\r$//' /usr/local/bin/start-bms.sh \
    && chmod +x /usr/local/bin/start-bms.sh

ENTRYPOINT ["/usr/local/bin/start-bms.sh"]
CMD ["apache2-foreground"]