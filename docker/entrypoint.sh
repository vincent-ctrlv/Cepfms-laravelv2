#!/bin/sh
# Container start-up for CEPFMS: check the config, prepare the writable
# folders (volumes may be mounted empty), cache the framework, optionally
# migrate, then hand over to Apache.
set -e
cd /var/www/html

if [ -z "$APP_KEY" ]; then
  echo "[cepfms] APP_KEY is not set. Generate one with 'php artisan key:generate --show' and add it to the environment." >&2
  exit 1
fi

# Listen on the platform's $PORT when it sets one (default 80).
if [ -n "$PORT" ] && [ "$PORT" != "80" ]; then
  sed -ri "s/^Listen 80\$/Listen ${PORT}/" /etc/apache2/ports.conf
  sed -ri "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf
fi

# storage/ and public/uploads/ are meant to be persistent volumes; a fresh
# volume starts empty, so recreate the structure Laravel and the app expect.
mkdir -p storage/app/private storage/app/public \
         storage/framework/cache/data storage/framework/sessions storage/framework/views \
         storage/logs bootstrap/cache public/uploads/submissions public/uploads/branding

# Built from the environment at start-up (not at build time, when it isn't known).
php artisan optimize

if [ "$RUN_MIGRATIONS" = "true" ]; then
  php artisan migrate --force
fi

# Last, so the caches written above belong to Apache's user too.
chown -R www-data:www-data storage bootstrap/cache
chown www-data:www-data public/uploads public/uploads/submissions public/uploads/branding

exec "$@"
