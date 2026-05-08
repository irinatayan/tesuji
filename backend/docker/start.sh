#!/bin/sh
set -e

php artisan migrate --force
php artisan db:seed --class=BotUsersSeeder --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan telegram:webhook:set || true

php artisan schedule:work &

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf