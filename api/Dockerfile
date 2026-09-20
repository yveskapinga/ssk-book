FROM composer:2 AS composer

FROM php:8.3-cli-alpine

RUN apk add --no-cache icu-dev libpq-dev poppler-utils \
    && docker-php-ext-install intl pdo_pgsql

COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY docker/uploads.ini /usr/local/etc/php/conf.d/uploads.ini
WORKDIR /app

ENV COMPOSER_ALLOW_SUPERUSER=1

COPY composer.json ./
# Local build: Symfony 7.3.* still flagged by Packagist advisories; unblock install.
RUN composer config audit.block-insecure false \
    && composer install --no-interaction --prefer-dist --no-scripts

COPY . .
RUN composer dump-autoload --optimize && chmod +x bin/console

EXPOSE 8080
CMD ["sh", "-c", "rm -rf /app/var/cache/* /app/var/cache/.* 2>/dev/null; composer config audit.block-insecure false && composer install --no-interaction --no-scripts && php bin/console cache:clear --no-warmup || true && php -S 0.0.0.0:8080 -t public"]
