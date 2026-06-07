#!/bin/sh
set -e
cd /app

# Ensure the writable directory structure exists (in case a volume is mounted).
mkdir -p \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

if [ -z "$APP_KEY" ]; then
    echo "FATAL: APP_KEY is empty. Set it in Coolify."
    echo "Generate one with:  echo \"base64:\$(openssl rand -base64 32)\""
    exit 1
fi

# Cache config/routes/views with the runtime environment.
php artisan optimize:clear || true
php artisan config:cache
php artisan route:cache || true
php artisan view:cache || true
php artisan storage:link 2>/dev/null || true

# Run migrations from the app container only (RUN_MIGRATIONS=false on the worker).
if [ "${RUN_MIGRATIONS:-true}" != "false" ]; then
    echo "Waiting for the database, then migrating..."
    n=0
    until php artisan migrate --force; do
        n=$((n + 1))
        if [ "$n" -ge 30 ]; then
            echo "FATAL: database not reachable after 30 attempts."
            exit 1
        fi
        echo "  migrate failed (attempt $n/30); retrying in 2s..."
        sleep 2
    done
fi

exec "$@"
