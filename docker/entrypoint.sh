#!/bin/sh
set -e

cd /var/www/html

# Generar nginx.conf con el puerto asignado ($PORT)
envsubst '${PORT}' < /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf

mkdir -p \
    storage/framework/cache storage/framework/sessions storage/framework/views \
    storage/app/private storage/app/public storage/logs \
    bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

# Descubrimiento de paquetes y migraciones (con reintentos: la BD puede tardar en estar lista)
php artisan package:discover --ansi || true

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    i=0
    until php artisan migrate --force; do
        i=$((i+1))
        if [ "$i" -ge 15 ]; then
            echo "Las migraciones fallaron tras $i intentos" >&2
            exit 1
        fi
        echo "Esperando base de datos... (intento $i)"
        sleep 3
    done
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache

exec supervisord -c /etc/supervisord.conf
