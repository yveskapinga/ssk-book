FROM composer:2 AS composer

FROM php:8.3-cli-alpine

RUN apk add --no-cache icu-dev libpq-dev poppler-utils \
    && docker-php-ext-install intl pdo_pgsql

COPY --from=composer /usr/bin/composer /usr/local/bin/composer
WORKDIR /app

COPY composer.json ./
RUN composer install --no-interaction --prefer-dist --no-scripts

COPY . .
RUN composer dump-autoload --optimize && chmod +x bin/console

EXPOSE 8080
CMD ["sh", "-c", "composer install --no-interaction && php -S 0.0.0.0:8080 -t public"]
