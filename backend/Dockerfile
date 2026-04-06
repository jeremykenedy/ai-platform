FROM dunglas/frankenphp:latest-php8.4-alpine AS base

RUN install-php-extensions \
    pdo_pgsql \
    pgsql \
    redis \
    pcntl \
    intl \
    zip \
    bcmath \
    opcache \
    gd

WORKDIR /app

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

FROM base AS development

RUN install-php-extensions xdebug

COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader

COPY . .
RUN composer dump-autoload --optimize

FROM base AS production

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --optimize-autoloader

COPY . .
RUN composer dump-autoload --optimize --no-dev

RUN mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 80 443

HEALTHCHECK --interval=30s --timeout=10s --retries=3 \
    CMD curl -f http://localhost/api/health || exit 1
