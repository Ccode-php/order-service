FROM php:8.3-cli

RUN apt-get update && apt-get install -y zip unzip git curl libzip-dev \
    && docker-php-ext-install pdo pdo_mysql

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/order-service

COPY . .

RUN composer install --no-interaction || true

EXPOSE 8000
CMD php artisan serve --host=0.0.0.0 --port=${APP_PORT:-8000}
