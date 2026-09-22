#!/bin/sh
# Стартовый скрипт контейнера. Первый аргумент выбирает роль:
#   serve  — веб-сервер с админкой (по умолчанию)
#   queue  — воркер очереди, разбирает рассылки
#   bot    — бот в режиме long polling, когда webhook некуда направить
set -e

cd /app

ROLE="${1:-serve}"

mkdir -p \
    bootstrap/cache \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs

# Ключ приложения обязателен: без него не расшифровать сессии и куки.
# В compose он приходит из .env, на хостинге — из панели.
if [ -z "${APP_KEY}" ]; then
    echo "APP_KEY не задан — генерирую временный (сессии не переживут перезапуск)."
    php artisan key:generate --force --show > /tmp/app_key
    APP_KEY="$(cat /tmp/app_key)"
    export APP_KEY
fi

# MySQL в соседнем контейнере поднимается дольше, чем приложение.
echo "Жду базу ${DB_HOST}:${DB_PORT}..."
i=0
until php -r 'exit(@fsockopen(getenv("DB_HOST"), (int) getenv("DB_PORT")) ? 0 : 1);'; do
    i=$((i + 1))
    if [ "$i" -ge 60 ]; then
        echo "База не ответила за 60 секунд." >&2
        exit 1
    fi
    sleep 1
done

# Миграции и кеш конфигов накатывает только веб-контейнер: иначе три роли
# стартуют одновременно и лезут в одни и те же таблицы.
if [ "$ROLE" = "serve" ]; then
    php artisan migrate --force

    # Сидеры идемпотентны (updateOrCreate): каталог и учётка администратора
    # появляются на пустой базе и не дублируются на существующей.
    php artisan db:seed --force

    php artisan storage:link || true

    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan filament:optimize || true
else
    # Остальные роли ждут, пока веб-контейнер накатит миграции. Проверять само
    # наличие таблицы migrations мало: она появляется первой, а jobs и cache —
    # позже, и воркер успевал стартовать между ними. Ждём, пока не останется
    # ни одной невыполненной миграции.
    echo "Жду готовности схемы базы..."
    i=0
    while : ; do
        if php artisan migrate:status --no-ansi > /tmp/migrate-status 2>/dev/null &&
           ! grep -qi 'pending' /tmp/migrate-status; then
            break
        fi

        i=$((i + 1))
        if [ "$i" -ge 90 ]; then
            echo "Схема базы так и не появилась." >&2
            exit 1
        fi
        sleep 2
    done
fi

case "$ROLE" in
    serve)
        # На хостингах порт приходит в $PORT, TLS терминируется на их стороне.
        export SERVER_NAME=":${PORT:-8080}"
        exec frankenphp run --config /etc/caddy/Caddyfile
        ;;
    queue)
        exec php artisan queue:work --tries=3 --sleep=1 --max-time=3600
        ;;
    bot)
        exec php artisan nutgram:run
        ;;
    *)
        # Любая другая команда выполняется как есть: docker compose run app php artisan ...
        exec "$@"
        ;;
esac
