# syntax=docker/dockerfile:1
FROM node:22-bookworm-slim AS web
WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js tailwind.config.cjs postcss.config.cjs ./
COPY resources ./resources
RUN npm run build

FROM php:8.4-apache-bookworm AS php-base
ARG PHP_BUILD_JOBS=1
RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"${PHP_BUILD_JOBS}" pdo_mysql mbstring bcmath gd zip opcache \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /var/www/html

FROM php-base AS vendor
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY composer.json composer.lock ./
# No host .env, application scripts, or database commands at this stage.
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts --no-autoloader
COPY app ./app
COPY database ./database
RUN composer dump-autoload --no-dev --optimize --no-scripts

FROM php-base AS runtime
ENV APP_ENV=production APP_DEBUG=false LOG_CHANNEL=stderr \
    SESSION_DRIVER=file CACHE_STORE=file QUEUE_CONNECTION=sync
COPY devops/lab-act-3/docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY devops/lab-act-3/docker/lab-version.txt /usr/local/share/invoiz-lab-version.txt
COPY --chown=www-data:www-data app ./app
COPY --chown=www-data:www-data bootstrap ./bootstrap
COPY --chown=www-data:www-data config ./config
COPY --chown=www-data:www-data database ./database
COPY --chown=www-data:www-data resources ./resources
COPY --chown=www-data:www-data routes ./routes
COPY --chown=www-data:www-data public ./public
COPY --chown=www-data:www-data artisan composer.json composer.lock ./
COPY --from=vendor --chown=www-data:www-data /var/www/html/vendor ./vendor
COPY --from=web --chown=www-data:www-data /build/public/build ./public/build
RUN mkdir -p storage/app/public storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && ln -s /var/www/html/storage/app/public public/storage \
    && chown -R www-data:www-data storage bootstrap/cache
EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD php -r '$h = @get_headers("http://127.0.0.1/up"); exit($h && str_contains($h[0], "200") ? 0 : 1);'
CMD ["apache2-foreground"]
