#!/bin/bash
set -e

echo "Generating JWT keys if missing..."
php bin/console lexik:jwt:generate-keypair --skip-if-exists

echo "Running database migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

echo "Warming production cache..."
php bin/console cache:clear --env=prod --no-debug

echo "Starting PHP-FPM..."
php-fpm -F &
PHP_PID=$!

echo "Waiting for PHP-FPM to start..."
sleep 2

echo "Starting Nginx..."
nginx -g "daemon off;"

wait $PHP_PID
