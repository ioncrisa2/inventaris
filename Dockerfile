# syntax=docker/dockerfile:1.7

FROM node:22-bookworm-slim AS frontend

WORKDIR /build

COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts

COPY resources ./resources
COPY public ./public
COPY vite.config.js ./
RUN npm run build

FROM dunglas/frankenphp:1-php8.4-bookworm AS app

ENV APP_ENV=production \
    APP_DEBUG=false

RUN apt-get update \
    && apt-get install -y --no-install-recommends curl libcap2-bin \
    && rm -rf /var/lib/apt/lists/* \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && install-php-extensions \
        bcmath \
        exif \
        gd \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_mysql \
        zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php.ini /usr/local/etc/php/conf.d/99-app.ini
COPY Caddyfile /etc/frankenphp/Caddyfile

WORKDIR /app
COPY . .

# Composer @php scripts are run explicitly through FrankenPHP's CLI SAPI.
RUN rm -f bootstrap/cache/*.php \
    && composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --no-scripts \
        --optimize-autoloader \
        --prefer-dist \
    && frankenphp php-cli artisan package:discover --ansi \
    && frankenphp php-cli artisan storage:link \
    && mkdir -p \
        storage/app/private \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
        /data/caddy \
        /config/caddy \
    && chown -R www-data:www-data \
        /app/storage \
        /app/bootstrap/cache \
        /data \
        /config \
    && setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/frankenphp

COPY --from=frontend --chown=www-data:www-data /build/public/build /app/public/build

USER www-data

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD curl --fail --silent --show-error http://127.0.0.1/up > /dev/null || exit 1
