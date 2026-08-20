FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --ignore-platform-req=ext-pcntl \
    --ignore-platform-req=ext-posix

FROM php:8.3-fpm-alpine AS runtime

RUN apk add --no-cache \
        libpq \
        icu-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        libzip-dev \
        oniguruma-dev \
        curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pgsql \
        pcntl \
        intl \
        gd \
        mbstring \
        zip \
        bcmath \
    && apk del icu-dev libpng-dev libjpeg-turbo-dev freetype-dev libzip-dev oniguruma-dev

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

COPY --from=vendor /app/vendor ./vendor

RUN mkdir -p storage/framework/{sessions,views,cache} \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 8000 8080

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]