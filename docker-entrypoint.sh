#!/bin/sh

echo "→ Waiting for database to be ready..."
sleep 3

echo "→ Running database migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

echo "→ Syncing schema (safety net)..."
php bin/console doctrine:schema:update --force --no-interaction 2>/dev/null || true

echo "→ Starting FrankenPHP..."
exec frankenphp run --config /etc/caddy/Caddyfile
