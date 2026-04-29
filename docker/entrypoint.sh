#!/bin/sh
set -e

if [ -z "$(ls -A /var/www/html/public-volume 2>/dev/null)" ]; then
    cp -r /var/www/html/public/. /var/www/html/public-volume/
fi

exec php-fpm
