# syntax=docker/dockerfile:1

FROM composer:2.10.3 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader \
    --ignore-platform-reqs

FROM dunglas/frankenphp:1.13.4-php8.4.27-trixie AS runtime

RUN install-php-extensions pdo_pgsql opcache

ENV PHP_UPLOAD_MAX_FILESIZE=20M \
    PHP_POST_MAX_SIZE=110M

RUN useradd --create-home --uid 1000 appuser \
    && chown -R appuser:appuser /data/caddy /config/caddy

WORKDIR /app

COPY docker/php.ini $PHP_INI_DIR/conf.d/zz-app.ini
COPY docker/Caddyfile /etc/frankenphp/Caddyfile
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

COPY . .
COPY --from=vendor /app/vendor ./vendor

RUN php artisan package:discover --ansi \
    && chown -R appuser:appuser storage bootstrap/cache \
    && chmod +x /usr/local/bin/entrypoint.sh

USER appuser

EXPOSE 8000

ENTRYPOINT ["entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
