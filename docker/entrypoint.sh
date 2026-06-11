#!/bin/sh
set -e

# Framework caches depend on the runtime environment: rebuilt on every start.
php artisan config:cache
php artisan route:cache
php artisan event:cache

# One-off task or single node only.
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
fi

exec "$@"
