#!/bin/bash
set -eo pipefail

PORT="${PORT:-80}"
export PORT

# Railway env overrides .env; .env.docker supplies non-secret defaults (TRUSTED_PROXIES, etc.)
if [ ! -f /app/.env ]; then
  cp /app/.env.docker /app/.env
fi

if [ -z "${APP_SECRET:-}" ]; then
  echo "ERROR: Set APP_SECRET in Railway service variables."
  exit 1
fi

export JWT_PASSPHRASE="${JWT_PASSPHRASE:-}"
export TRUSTED_PROXIES="${TRUSTED_PROXIES:-127.0.0.1,REMOTE_ADDR}"

if ! grep -q "listen ${PORT} default_server" /etc/nginx/conf.d/symfony.conf; then
  sed -i "s/listen 80 default_server/listen ${PORT} default_server/" /etc/nginx/conf.d/symfony.conf
fi

run_console() {
  su -s /bin/sh www-data -c "$1"
}

echo "Generating JWT keys if missing..."
run_console "php bin/console lexik:jwt:generate-keypair --skip-if-exists" || {
  echo "WARNING: JWT key generation failed; continuing startup"
}

if [ -n "${DATABASE_URL:-}" ]; then
  echo "Running database migrations..."
  run_console "php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration" || {
    echo "WARNING: migrations failed; continuing startup (check DATABASE_URL)"
  }
else
  echo "DATABASE_URL not set; skipping migrations"
fi

echo "Warming production cache..."
run_console "php bin/console cache:clear --env=prod --no-debug" || {
  echo "WARNING: cache clear failed; continuing startup"
}

chown -R www-data:www-data /app/var /app/config/jwt 2>/dev/null || true

echo "Starting PHP-FPM..."
php-fpm -D

echo "Testing Nginx configuration..."
nginx -t

echo "Starting Nginx on port ${PORT}..."
exec nginx -g "daemon off;"
