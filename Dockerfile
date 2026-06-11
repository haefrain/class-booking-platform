# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# Stage 1 — vendor: production dependencies only
# ---------------------------------------------------------------------------
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

# ---------------------------------------------------------------------------
# Stage 2 — assets: Wayfinder needs PHP, Vite needs Node → one stage with both
# ---------------------------------------------------------------------------
FROM php:8.4-cli-alpine AS assets

RUN apk add --no-cache nodejs npm

WORKDIR /app

COPY . .
COPY --from=vendor /app/vendor ./vendor

# Wayfinder boots the app to read routes: a throwaway key is enough.
RUN cp .env.example .env \
    && php artisan key:generate \
    && php artisan wayfinder:generate --with-form \
    && npm ci \
    && npm run build

# ---------------------------------------------------------------------------
# Stage 3 — app: php-fpm runtime, non-root, opcache tuned for production
# ---------------------------------------------------------------------------
FROM php:8.4-fpm-alpine AS app

RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS linux-headers \
    && docker-php-ext-install pdo_mysql opcache pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

WORKDIR /var/www/html

COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build

# Runtime caches (config/route/event) depend on env vars: built by the
# entrypoint at container start, never baked into the image.
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh \
    && mkdir -p bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

# Build-time proof that no config file references a dev-only class
# (composer --no-dev would explode here, not in production).
RUN cp .env.example .env \
    && php artisan key:generate \
    && php artisan config:cache \
    && php artisan config:clear \
    && rm -f .env

USER www-data

EXPOSE 9000

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
