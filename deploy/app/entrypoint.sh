#!/bin/sh
set -e

# Determine the port to listen on (Railway/Heroku set $PORT, default 80)
PORT="${PORT:-80}"

# Substitute the port into the nginx site config
sed -i "s/__PORT__/${PORT}/g" /etc/nginx/sites-available/laravel

# Enable the site
if [ -f /etc/nginx/sites-enabled/default ]; then
  rm -f /etc/nginx/sites-enabled/default
fi
ln -sf /etc/nginx/sites-available/laravel /etc/nginx/sites-enabled/laravel

# Ensure PHP-FPM socket directory exists
mkdir -p /run/php
chown -R www-data:www-data /run/php

# Run migrations in production if the env flag is set
if [ "${RUN_MIGRATIONS}" = "true" ]; then
  echo "Running migrations..."
  php artisan migrate --force
fi

if [ "${RUN_SEEDERS}" = "true" ]; then
  echo "Running seeders..."
  php artisan db:seed --force
fi

# Cache Laravel config for production speed
if [ "${APP_ENV}" = "production" ]; then
  php artisan config:cache || true
  php artisan route:cache || true
  php artisan view:cache || true
fi

echo "Starting PHP-FPM..."
php-fpm -D

echo "Starting Nginx on port ${PORT}..."
nginx -g "daemon off;"
