#!/bin/bash

set -e

echo "Starting deployment..."

cd /home/u350975603/domains/mudassirhusain.in/public_html/admin

echo "Pulling latest code..."
git pull origin main

echo "Installing PHP dependencies..."
/opt/alt/php83/usr/bin/php /usr/local/bin/composercomposer install --no-dev --optimize-autoloader

echo "Running migrations..."
/opt/alt/php83/usr/bin/php artisan migrate --force

echo "Clearing cache..."
/opt/alt/php83/usr/bin/php artisan optimize:clear
/opt/alt/php83/usr/bin/php artisan config:clear
/opt/alt/php83/usr/bin/php artisan route:clear
/opt/alt/php83/usr/bin/php artisan view:clear
/opt/alt/php83/usr/bin/php artisan cache:clear

echo "Caching config and routes..."
/opt/alt/php83/usr/bin/php artisan config:cache
/opt/alt/php83/usr/bin/php artisan route:cache

echo "Fixing permissions..."
chmod -R 775 storage bootstrap/cache

echo "Deployment completed successfully!"
