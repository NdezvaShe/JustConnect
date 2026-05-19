FROM composer:2 AS composer

FROM php:8.4-cli AS vendor

WORKDIR /app

COPY --from=composer /usr/bin/composer /usr/bin/composer
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/*

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --no-autoloader

COPY . .
RUN composer dump-autoload --optimize --no-dev --no-scripts

FROM php:8.4-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev libpq-dev libzip-dev unzip \
    && docker-php-ext-install mbstring pdo_mysql pdo_pgsql pdo_sqlite zip \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY --from=vendor /app /var/www/html
COPY docker/render-entrypoint.sh /usr/local/bin/render-entrypoint

RUN sed -ri -e 's!/var/www/html!/var/www/html/public!g' \
        /etc/apache2/sites-available/*.conf \
        /etc/apache2/apache2.conf \
        /etc/apache2/conf-available/*.conf \
    && chmod +x /usr/local/bin/render-entrypoint \
    && mkdir -p storage/app/private storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache database \
    && chown -R www-data:www-data storage bootstrap/cache database

EXPOSE 8000

ENTRYPOINT ["render-entrypoint"]
