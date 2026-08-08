#!/bin/sh
set -e

required_variables='APP_KEY DB_CONNECTION DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD'

for required_variable in $required_variables; do
    eval "required_value=\${$required_variable:-}"

    if [ -z "$required_value" ]; then
        echo "VetFlow startup failed: $required_variable must be configured." >&2
        exit 1
    fi
done

if [ "$DB_CONNECTION" != 'pgsql' ]; then
    echo 'VetFlow startup failed: DB_CONNECTION must be pgsql on Render.' >&2
    exit 1
fi

if [ "${APP_DEBUG:-false}" != 'false' ]; then
    echo 'VetFlow startup failed: APP_DEBUG must be false in this deployment.' >&2
    exit 1
fi

: "${PORT:=8080}"

php artisan optimize:clear
php artisan package:discover --ansi --no-interaction
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache

envsubst '${PORT}' < /etc/nginx/http.d/default.conf.template > /etc/nginx/http.d/default.conf

exec /usr/bin/supervisord -c /etc/supervisord.conf
