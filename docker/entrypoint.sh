#!/bin/sh
set -e

cp -r /var/www/html/public/. /var/www/html/public-volume/

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

exec php-fpm
