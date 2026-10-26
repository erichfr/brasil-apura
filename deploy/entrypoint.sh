#!/bin/sh
set -eu
cd /var/www/html
mkdir -p storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs
if [ "${APP_ENV:-}" = "production" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi
exec docker-php-entrypoint "$@"
