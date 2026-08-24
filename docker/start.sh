#!/bin/sh
set -e

require_variable() {
    variable="$1"
    value="$(printenv "$variable" 2>/dev/null || true)"

    if [ -z "$value" ]; then
        echo "VetFlow startup failed: $variable must be configured." >&2
        exit 1
    fi
}

for variable in APP_KEY DB_CONNECTION; do
    require_variable "$variable"
done

if [ -z "${DB_URL:-${DATABASE_URL:-}}" ]; then
    for variable in DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD; do
        require_variable "$variable"
    done
fi

if [ "$DB_CONNECTION" != 'pgsql' ]; then
    echo 'VetFlow startup failed: DB_CONNECTION must be pgsql on Render.' >&2
    exit 1
fi

if [ "${APP_DEBUG:-false}" != 'false' ]; then
    echo 'VetFlow startup failed: APP_DEBUG must be false in this deployment.' >&2
    exit 1
fi

: "${PORT:=8080}"

php artisan migrate --force

if [ "${VETFLOW_SEED_WALKTHROUGH:-false}" = 'true' ]; then
    case "${APP_URL:-}" in
        'https://demo.vetflowsys.com.br'|'https://vetflow-demo.onrender.com') ;;
        *)
            echo 'VetFlow startup failed: walkthrough seeding is restricted to the demonstration service.' >&2
            exit 1
            ;;
    esac

    php artisan db:seed \
        --class='Database\Seeders\WalkthroughDemoSeeder' \
        --force \
        --no-interaction

    echo 'VetFlow fictitious walkthrough data seeded.'
fi

if [ "${VETFLOW_BOOTSTRAP_ADMIN:-false}" = 'true' ]; then
    for variable in VETFLOW_BOOTSTRAP_ADMIN_NAME VETFLOW_BOOTSTRAP_ADMIN_EMAIL VETFLOW_BOOTSTRAP_ADMIN_PASSWORD; do
        require_variable "$variable"
    done

    php artisan vetflow:admin:create \
        --name="$VETFLOW_BOOTSTRAP_ADMIN_NAME" \
        --email="$VETFLOW_BOOTSTRAP_ADMIN_EMAIL" \
        --password="$VETFLOW_BOOTSTRAP_ADMIN_PASSWORD" \
        --no-interaction

    echo 'VetFlow administrator bootstrap completed.'
fi

php artisan optimize:clear
php artisan package:discover --ansi --no-interaction
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache

envsubst '${PORT}' < /etc/nginx/http.d/default.conf.template > /etc/nginx/http.d/default.conf

exec /usr/bin/supervisord -c /etc/supervisord.conf
