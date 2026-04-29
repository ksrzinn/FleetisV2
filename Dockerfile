FROM php:8.3-fpm

WORKDIR /var/www/html

RUN apt-get update && apt-get install -y \
    git curl zip unzip nodejs npm \
    libpq-dev \
    libicu-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev
    
# extensões PHP corretas
RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    mbstring \
    xml \
    bcmath \
    zip \
    gd \
    opcache \
    calendar

RUN docker-php-ext-configure intl
RUN docker-php-ext-install intl

# composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# evita erro de ownership do git
RUN git config --global --add safe.directory /var/www/html

COPY . .

RUN mkdir -p bootstrap/cache storage/logs storage/framework/cache storage/framework/sessions storage/framework/views

RUN composer install --no-interaction --prefer-dist

RUN npm install && npm run build

RUN chmod -R 775 storage bootstrap/cache

EXPOSE 9000

CMD ["php-fpm"]