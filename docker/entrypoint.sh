#!/bin/sh
set -eu

case "${PORT:-10000}" in
    ''|*[!0-9]*)
        echo "PORT precisa ser um número inteiro." >&2
        exit 1
        ;;
esac

if [ -z "${APP_KEY:-}" ]; then
    echo "APP_KEY não configurada. Cadastre o secret no Render antes do deploy." >&2
    exit 1
fi

sed "s/__PORT__/${PORT:-10000}/g" \
    /etc/apache2/sites-available/000-default.conf.template \
    > /etc/apache2/sites-available/000-default.conf
printf 'Listen %s\n' "${PORT:-10000}" > /etc/apache2/ports.conf

mkdir -p \
    bootstrap/cache \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs

if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
    sqlite_database="${DB_DATABASE:-/var/www/html/database/database.sqlite}"
    mkdir -p "$(dirname "$sqlite_database")"
    touch "$sqlite_database"
    chown www-data:www-data "$sqlite_database"
fi

chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

php artisan storage:link --force
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan migrate --force
php artisan optimize

exec "$@"
