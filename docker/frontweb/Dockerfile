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
        python3.11 python3.11-venv python3-pip \
        libpq-dev gcc \
        git unzip libzip-dev \
    ; \
    ln -sf /usr/bin/python3.11 /usr/local/bin/python; \
    ln -sf /usr/bin/python3.11 /usr/bin/python3; \
    docker-php-ext-install pdo pdo_pgsql zip; \
    a2enmod rewrite; \
    rm -rf /var/lib/apt/lists/*; \
    apt-get clean; \
    rm -rf /var/cache/apt/archives/* /tmp/* /var/tmp/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .

COPY docker/common_python_venv.sh /common_python_venv.sh
COPY docker/frontweb_entrypoint.sh /usr/local/bin/start-frontweb.sh

RUN chmod +x /common_python_venv.sh /usr/local/bin/start-frontweb.sh

ENTRYPOINT ["/usr/local/bin/start-frontweb.sh"]
CMD ["apache2-foreground"]