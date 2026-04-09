#!/bin/sh

echo "→ Starting FrankenPHP in background..."
frankenphp run --config /etc/caddy/Caddyfile &
FRANKEN_PID=$!

echo "→ Waiting for database to be ready..."
sleep 5

echo "→ Running database migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration || true

echo "→ Syncing schema (safety net)..."
php bin/console doctrine:schema:update --force --no-interaction 2>/dev/null || true

echo "→ Migrations done, FrankenPHP already running (PID: $FRANKEN_PID)"
wait $FRANKEN_PID
