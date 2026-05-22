#!/bin/bash
set -euo pipefail

PORT="${PORT:-80}"
export PORT

# Railway injects PORT at runtime; nginx must listen on it (not hardcoded 80).
sed -i "s/listen 80 default_server/listen ${PORT} default_server/" /etc/nginx/conf.d/symfony.conf

export JWT_PASSPHRASE="${JWT_PASSPHRASE:-}"

echo "Generating JWT keys if missing..."
php bin/console lexik:jwt:generate-keypair --skip-if-exists || {
  echo "WARNING: JWT key generation failed; continuing startup"
}

if [ -n "${DATABASE_URL:-}" ]; then
  echo "Running database migrations..."
  php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration || {
    echo "WARNING: migrations failed; continuing startup (check DATABASE_URL)"
  }
else
  echo "DATABASE_URL not set; skipping migrations"
fi

echo "Warming production cache..."
php bin/console cache:clear --env=prod --no-debug || {
  echo "WARNING: cache clear failed; continuing startup"
}

echo "Starting PHP-FPM..."
php-fpm -F &
PHP_PID=$!

echo "Waiting for PHP-FPM to start..."
sleep 2
if ! kill -0 "$PHP_PID" 2>/dev/null; then
  echo "ERROR: PHP-FPM failed to start"
  exit 1
fi

echo "Starting Nginx on port ${PORT}..."
exec nginx -g "daemon off;"
