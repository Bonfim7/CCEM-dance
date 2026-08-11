# syntax=docker/dockerfile:1

FROM node:22-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json* .npmrc ./
RUN if [ -f package-lock.json ]; then npm ci; else npm install; fi

COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build


FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --no-scripts

COPY . .
RUN composer dump-autoload \
    --no-dev \
    --optimize \
    --classmap-authoritative \
    --no-interaction


FROM php:8.3-apache-bookworm AS runtime

ENV APP_ENV=production \
    APP_DEBUG=false \
    FILESYSTEM_DISK=s3 \
    LOG_CHANNEL=stderr \
    LOG_LEVEL=info \
    MEDIA_REQUIRE_CLOUD_DISK=true \
    PORT=10000

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libicu-dev \
        libcurl4-openssl-dev \
        libonig-dev \
        libpq-dev \
        libsqlite3-dev \
        libzip-dev \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        curl \
        intl \
        mbstring \
        opcache \
        pdo_mysql \
        pdo_pgsql \
        pdo_sqlite \
        zip \
    && a2enmod expires headers rewrite \
    && printf 'ServerName 127.0.0.1\n' > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY --from=vendor /app ./
COPY --from=frontend /app/public/build ./public/build
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf.template
COPY docker/php-production.ini /usr/local/etc/php/conf.d/ccem-production.ini
COPY docker/entrypoint.sh /usr/local/bin/ccem-entrypoint

RUN chmod +x /usr/local/bin/ccem-entrypoint \
    && mkdir -p \
        bootstrap/cache \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 10000

ENTRYPOINT ["ccem-entrypoint"]
CMD ["apache2-foreground"]
