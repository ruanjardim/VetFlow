FROM serversideup/php:8.2-fpm-nginx-alpine

USER root

RUN install-php-extensions \
    pdo_pgsql \
    intl \
    bcmath \
    zip

WORKDIR /var/www/html

COPY --chown=www-data:www-data . .

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader

RUN npm ci && npm run build && rm -rf node_modules

RUN mkdir -p \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

USER www-data

ENV PHP_OPCACHE_ENABLE=1
ENV AUTORUN_ENABLED=false

CMD ["sh", "/var/www/html/docker/start.sh"]
