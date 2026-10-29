#!/bin/sh
set -e

if [ -z "$APP_KEY" ]; then
    export APP_KEY="$(php artisan key:generate --show)"
    echo "APP_KEY is empty: generated a temporary key for this container."
fi

echo "Waiting for PostgreSQL at ${DB_HOST}:${DB_PORT}..."
until php -r 'new PDO("pgsql:host=".getenv("DB_HOST").";port=".getenv("DB_PORT").";dbname=".getenv("DB_DATABASE"), getenv("DB_USERNAME"), getenv("DB_PASSWORD"));' 2>/dev/null; do
    sleep 2
done
echo "PostgreSQL is ready."

if [ "$RUN_MIGRATIONS" = "true" ]; then
    php artisan migrate --force
    php artisan db:seed --force
fi

php artisan config:cache
php artisan route:cache

exec "$@"
