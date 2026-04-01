#!/bin/sh

echo "→ Waiting for database to be ready..."
sleep 3

echo "→ Running database migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

echo "→ Starting FrankenPHP..."
exec frankenphp run --config /etc/caddy/Caddyfile
