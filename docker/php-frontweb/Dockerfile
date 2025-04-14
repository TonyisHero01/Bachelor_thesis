FROM php:8.2-apache

# 安装系统依赖和 PHP 扩展
RUN apt-get update && apt-get install -y \
        git unzip libpq-dev libzip-dev zip \
    && docker-php-ext-install pdo pdo_pgsql zip

# 启用 Apache rewrite 模块
RUN a2enmod rewrite

# 安装 Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# 设置工作目录
WORKDIR /var/www/html

# 默认运行 apache
CMD ["apache2-foreground"]