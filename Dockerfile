# ---- SmartPOS Pro API (Laravel 13 / PHP 8.4) ----
FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
        git curl libpng-dev libzip-dev icu-dev postgresql-dev oniguruma-dev bash \
    && docker-php-ext-configure intl \
    && docker-php-ext-install pdo pdo_pgsql pgsql bcmath intl zip gd pcntl

# Redis extension for cache/queue.
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/local.ini /usr/local/etc/php/conf.d/local.ini

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --optimize

EXPOSE 9000
CMD ["php-fpm"]
