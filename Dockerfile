# Образ приложения: FrankenPHP отдаёт public/ напрямую, без связки nginx + php-fpm.
# Один и тот же образ поднимает три роли — веб, воркер очереди и бота;
# что именно запускать, решает аргумент команды (см. compose.yaml).

# Фронт Mini App собирается отдельно: Node нужен только на этапе сборки,
# в итоговый образ попадает лишь готовый public/build.
FROM node:24-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

FROM dunglas/frankenphp:php8.4

# intl нужен Filament, pdo_mysql — базе, pcntl — корректной остановке queue:work.
RUN install-php-extensions \
        intl \
        zip \
        gd \
        exif \
        bcmath \
        pdo_mysql \
        pcntl \
        opcache

WORKDIR /app

# Значения по умолчанию, чтобы контейнер поднимался и без внешних переменных.
# compose и панель хостинга перекрывают их своими.
ENV APP_ENV=production \
    APP_DEBUG=false \
    APP_LOCALE=ru \
    APP_FALLBACK_LOCALE=en \
    APP_TIMEZONE=Europe/Moscow \
    LOG_CHANNEL=stderr \
    DB_CONNECTION=mysql \
    DB_HOST=mysql \
    DB_PORT=3306 \
    DB_DATABASE=telegram_bot_admin \
    DB_USERNAME=telegram \
    SESSION_DRIVER=database \
    CACHE_STORE=database \
    QUEUE_CONNECTION=database

COPY --from=composer/composer:2-bin /composer /usr/bin/composer

# Сначала только манифесты — слой с зависимостями переиспользуется между сборками.
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --prefer-dist \
        --no-scripts \
        --no-autoloader

COPY . .
COPY --from=assets /app/public/build ./public/build

RUN composer dump-autoload --optimize --no-dev \
    && php artisan package:discover --ansi \
    && php artisan filament:assets \
    && chmod +x docker/entrypoint.sh

EXPOSE 8080

ENTRYPOINT ["/app/docker/entrypoint.sh"]
CMD ["serve"]
