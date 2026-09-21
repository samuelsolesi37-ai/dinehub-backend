FROM php:8.3-cli

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y \
        git \
        unzip \
        libpq-dev \
        libzip-dev \
        libonig-dev \
    && docker-php-ext-install \
        pdo_pgsql \
        mbstring \
        zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY . .

RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --prefer-dist

RUN mkdir -p \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    bootstrap/cache

RUN chmod -R 775 storage bootstrap/cache

EXPOSE 10000

CMD sh -c "php artisan migrate --force && php artisan optimize:clear && php artisan config:cache && php artisan serve --host=0.0.0.0 --port=\${PORT:-10000}"