# ── Stage 1: dependencias PHP ────────────────────────────────────────────────
FROM composer:2 AS deps
WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer install --no-dev --optimize-autoloader --no-scripts

# ── Stage 2: build del frontend (Vue 3 + Vite) ───────────────────────────────
FROM node:22-alpine AS frontend
WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

# vendor solo se necesita porque app.css referencia vistas de paginación de Tailwind
COPY --from=deps /app/vendor ./vendor
COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

# ── Stage 3: runtime (nginx + php-fpm + supervisord) ─────────────────────────
FROM php:8.3-fpm-alpine AS runtime

RUN apk add --no-cache \
        nginx supervisor curl gettext \
        icu-libs libpng libjpeg-turbo freetype libwebp libxml2 oniguruma libzip \
    && apk add --no-cache --virtual .build-deps \
        icu-dev libpng-dev libjpeg-turbo-dev freetype-dev libwebp-dev \
        oniguruma-dev zlib-dev libzip-dev linux-headers $PHPIZE_DEPS \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql mbstring zip gd bcmath intl opcache \
    && apk del .build-deps \
    && rm -rf /tmp/* /var/cache/apk/*

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php-fpm-clear-env.conf /usr/local/etc/php-fpm.d/zz-clear-env.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/nginx.conf.template /etc/nginx/nginx.conf.template
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh \
    && mkdir -p /var/www/html \
    && mkdir -p /var/log/nginx /run/nginx /var/log/supervisor \
    && rm -f /etc/nginx/http.d/default.conf

WORKDIR /var/www/html
COPY --from=deps --chown=www-data:www-data /app/vendor ./vendor
COPY --chown=www-data:www-data . .
COPY --from=frontend --chown=www-data:www-data /app/public/build ./public/build
RUN mkdir -p \
        storage/framework/cache storage/framework/sessions storage/framework/views \
        storage/app/private storage/app/public \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

ENV PORT=8080
EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD curl -fsS "http://127.0.0.1:${PORT}/up" || exit 1

ENTRYPOINT ["/entrypoint.sh"]
