#!/usr/bin/env sh
set -eu

: "${PORT:=8000}"
: "${APP_ENV:=production}"
: "${APP_DEBUG:=false}"
: "${APP_URL:=http://127.0.0.1:${PORT}}"
: "${DB_CONNECTION:=pgsql}"
: "${SESSION_DRIVER:=database}"
: "${CACHE_STORE:=database}"
: "${QUEUE_CONNECTION:=sync}"
: "${PREFERRED_AI:=gemini}"
: "${MAIL_MAILER:=log}"

export PORT APP_ENV APP_DEBUG APP_URL DB_CONNECTION
export SESSION_DRIVER CACHE_STORE QUEUE_CONNECTION PREFERRED_AI MAIL_MAILER

mkdir -p \
    storage/app/private \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

if [ "${DB_CONNECTION}" = "sqlite" ]; then
    : "${DB_DATABASE:=/var/www/html/database/database.sqlite}"
    export DB_DATABASE
    mkdir -p "$(dirname "$DB_DATABASE")"
    [ -f "$DB_DATABASE" ] || touch "$DB_DATABASE"
fi

chown -R www-data:www-data storage bootstrap/cache database || true

php artisan package:discover --ansi
php artisan migrate --force
php artisan storage:link || true
php artisan optimize:clear
php artisan config:cache
php artisan route:cache

sed -ri "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/:80/:${PORT}/" /etc/apache2/sites-available/000-default.conf

exec apache2-foreground
