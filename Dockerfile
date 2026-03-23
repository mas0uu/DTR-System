# syntax=docker/dockerfile:1

FROM node:22-alpine AS assets
WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY resources ./resources
COPY public ./public
COPY postcss.config.js tailwind.config.js tsconfig.json vite.config.js ./
RUN npm run build

FROM php:8.3-cli-bookworm
WORKDIR /var/www/html

ENV COMPOSER_ALLOW_SUPERUSER=1

RUN apt-get update \
    && apt-get install -y --no-install-recommends ca-certificates curl git unzip pkg-config libcurl4-openssl-dev libonig-dev libicu-dev libpq-dev libzip-dev libxml2-dev \
    && update-ca-certificates \
    && docker-php-ext-install bcmath curl dom intl mbstring pdo_pgsql xml zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress --no-scripts

COPY . .
COPY --from=assets /app/public/build ./public/build
COPY scripts/render-start.sh /usr/local/bin/render-start.sh

RUN sed -i 's/\r$//' /usr/local/bin/render-start.sh \
    && chmod +x /usr/local/bin/render-start.sh \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache

EXPOSE 10000

CMD ["/usr/local/bin/render-start.sh"]
