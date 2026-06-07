# syntax=docker/dockerfile:1

# ---- Stage 1: build (PHP + Node) ----
# Wayfinder runs `php artisan wayfinder:generate` during `npm run build`, so the
# asset build needs PHP, the app source, and vendor/ all present together.
FROM dunglas/frankenphp:php8.4 AS build

RUN install-php-extensions \
        pcntl \
        pdo_pgsql \
        pdo_mysql \
        pdo_sqlite \
        mbstring \
        bcmath \
        zip \
        intl \
    && apt-get update \
    && apt-get install -y --no-install-recommends curl ca-certificates git unzip \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-scripts

COPY package.json package-lock.json ./
RUN npm ci

COPY . .

# A throwaway key + env so artisan can boot during the Wayfinder/Vite build.
RUN cp .env.example .env \
    && php artisan key:generate \
    && npm run build \
    && composer install --no-dev --no-interaction --optimize-autoloader \
    && rm -rf node_modules .env

# ---- Stage 2: runtime (PHP 8.4 + FrankenPHP) ----
FROM dunglas/frankenphp:php8.4 AS app

RUN install-php-extensions \
        pcntl \
        pdo_pgsql \
        mbstring \
        bcmath \
        zip \
        intl \
        opcache

WORKDIR /app

COPY --from=build /app /app
COPY docker/Caddyfile /etc/frankenphp/Caddyfile
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh \
    && mkdir -p \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
# Default command = web server. The worker container overrides this (see compose).
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
