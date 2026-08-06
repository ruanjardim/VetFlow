#!/bin/sh
set -e

php artisan optimize:clear
php artisan migrate --force

exec /init
