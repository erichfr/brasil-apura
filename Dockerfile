# syntax=docker/dockerfile:1
FROM php:8.3-apache-bookworm AS php-base
RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev libonig-dev libzip-dev unzip \
    && docker-php-ext-install -j2 bcmath intl mbstring pdo_mysql zip opcache \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /var/www/html

FROM php-base AS dependencies
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1
COPY . .
RUN mkdir -p storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && composer install --no-dev --no-interaction --prefer-dist --no-scripts --optimize-autoloader \
    && php artisan package:discover --ansi

FROM node:24-bookworm-slim AS assets
WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY resources ./resources
COPY vite.config.js ./
RUN npm run build

FROM php-base AS runtime
COPY . .
COPY --from=dependencies /var/www/html/vendor ./vendor
COPY --from=dependencies /var/www/html/bootstrap/cache ./bootstrap/cache
COPY --from=assets /build/public/build ./public/build
COPY deploy/apache.conf /etc/apache2/sites-available/000-default.conf
COPY deploy/php-production.ini /usr/local/etc/php/conf.d/production.ini
COPY deploy/entrypoint.sh /usr/local/bin/brasil-apura-entrypoint
RUN mkdir -p storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod +x /usr/local/bin/brasil-apura-entrypoint
ENTRYPOINT ["brasil-apura-entrypoint"]
CMD ["apache2-foreground"]
