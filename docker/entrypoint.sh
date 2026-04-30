#!/bin/sh
set -e

if [ -z "$(ls -A /var/www/html/public-volume 2>/dev/null)" ]; then
    cp -r /var/www/html/public/. /var/www/html/public-volume/
fi

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

exec php-fpm
