FROM node:24.16.0-bookworm-slim AS assets
WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
COPY vite.config.js ./
RUN npm run build

FROM composer:2.10.3 AS composer

FROM php:8.2.34-cli-bookworm AS application
RUN apt-get update && apt-get install -y --no-install-recommends libonig-dev libzip-dev libicu-dev unzip git \
    && docker-php-ext-install -j2 pdo_mysql mbstring bcmath pcntl zip intl \
    && rm -rf /var/lib/apt/lists/* \
    && useradd --uid 1000 --create-home airline
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/app
COPY --chown=airline:airline . .
COPY --from=assets --chown=airline:airline /build/public/build ./public/build
RUN mkdir -p storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R airline:airline storage bootstrap/cache
USER airline
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
