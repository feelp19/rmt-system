# Verify the exact PHP 8.5 tag on hub.docker.com/r/dunglas/frankenphp before relying on it.
FROM dunglas/frankenphp:php8.5 AS base

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
RUN install-php-extensions pdo_mysql redis intl zip bcmath pcntl opcache

WORKDIR /app

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# Copy the WHOLE project (FrankenPHP/Laravel requirement), not just public/
COPY . /app
RUN composer dump-autoload --optimize --no-dev \
 && php artisan route:cache \
 && php artisan event:cache

# Worker mode is automatic via Octane. The custom Caddyfile (added in a later task) governs the listener (:80).
CMD ["php", "artisan", "octane:frankenphp", "--caddyfile=/app/Caddyfile", "--admin-port=2019", "--workers=4", "--max-requests=500"]
