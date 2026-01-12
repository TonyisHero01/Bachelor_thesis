FROM php:8.2-apache-bookworm

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends ca-certificates gnupg debian-archive-keyring; \
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
        git unzip libzip-dev \
        libpq-dev \
        gcc make pkg-config \
    ; \
    docker-php-ext-install pdo pdo_pgsql zip; \
    a2enmod rewrite; \
    rm -rf /var/lib/apt/lists/*; \
    apt-get clean; \
    rm -rf /var/cache/apt/archives/* /tmp/* /var/tmp/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .

COPY docker/bms_entrypoint.sh /usr/local/bin/start-bms.sh
RUN chmod +x /usr/local/bin/start-bms.sh

ENTRYPOINT ["/usr/local/bin/start-bms.sh"]
CMD ["apache2-foreground"]