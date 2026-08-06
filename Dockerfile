FROM node:22-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY resources ./resources
COPY public ./public
COPY vite.config.js ./

RUN npm run build


FROM serversideup/php:8.2-fpm-nginx-alpine

USER root

RUN install-php-extensions \
    pdo_pgsql \
    intl \
    bcmath \
    zip

WORKDIR /var/www/html

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

COPY . .

COPY --from=frontend /app/public/build ./public/build

RUN composer dump-autoload --optimize \
    && mkdir -p \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

USER www-data

ENV PHP_OPCACHE_ENABLE=1
ENV AUTORUN_ENABLED=true
ENV AUTORUN_LARAVEL_MIGRATION=true
ENV AUTORUN_LARAVEL_MIGRATION_ISOLATION=true
ENV AUTORUN_LARAVEL_MIGRATION_SEED=false
ENV AUTORUN_LARAVEL_STORAGE_LINK=true

EXPOSE 8080
