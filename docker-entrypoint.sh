#!/bin/sh
set -e

echo "→ Running database migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

echo "→ Starting FrankenPHP..."
exec frankenphp run --config /etc/caddy/Caddyfile
