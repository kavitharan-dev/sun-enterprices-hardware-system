#!/usr/bin/env bash
set -e

cd /var/www/html

echo "Installing PHP dependencies..."
composer install --no-dev --optimize-autoloader --working-dir=/var/www/html

if [ -n "${DATABASE_URL:-}" ] && [ -z "${DB_URL:-}" ]; then
  export DB_URL="$DATABASE_URL"
fi

if [ -n "${RENDER_EXTERNAL_URL:-}" ]; then
  export APP_URL="$RENDER_EXTERNAL_URL"
fi

# Render generateValue is raw base64; Laravel expects the base64: prefix
if [ -z "${APP_KEY:-}" ]; then
  export APP_KEY="$(php artisan key:generate --show)"
elif [[ "$APP_KEY" != base64:* ]]; then
  export APP_KEY="base64:${APP_KEY}"
fi

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache || true

if [ ! -L public/storage ]; then
  php artisan storage:link || true
fi

echo "Running migrations..."
php artisan migrate --force

USER_COUNT="$(php -r "require 'vendor/autoload.php'; \$app=require 'bootstrap/app.php'; \$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo App\Models\User::query()->count();")"
if [ "$USER_COUNT" = "0" ]; then
  echo "Seeding first-boot data..."
  php artisan db:seed --force --class=RolePermissionSeeder
  php artisan db:seed --force --class=SettingsSeeder
  php artisan db:seed --force --class=SunEnterpriseProductSeeder
  php artisan db:seed --force --class=SunEnterprisePriceSeeder
fi

echo "Caching..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Deploy script finished."
