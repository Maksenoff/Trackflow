#!/bin/sh
set -e

echo "→ Waiting for database to be ready..."
sleep 5

echo "→ Running database migrations..."
#php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration || true

echo "→ Warming up Symfony cache..."
php bin/console cache:warmup --no-interaction 2>/dev/null || true

echo "→ Starting FrankenPHP..."
exec frankenphp run --config /etc/caddy/Caddyfile
