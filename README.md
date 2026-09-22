# Telegram-бот для заявок + веб-админка на Laravel

Готовое решение для приёма заявок через Telegram: клиент выбирает услугу в боте
и оставляет заявку за минуту, администратор видит её в веб-панели, меняет статус,
отвечает клиенту прямо из админки и запускает рассылки по базе пользователей.

## Как посмотреть

Публичный демо-стенд пока не развёрнут, поэтому самый быстрый способ —
поднять проект локально одной командой:

```bash
cp .env.example .env
docker compose run --rm app php artisan key:generate --show   # впишите результат в APP_KEY
docker compose up -d
```

Админка откроется на <http://localhost:8080/admin>, учётные данные — из
`ADMIN_EMAIL` и `ADMIN_PASSWORD` в `.env` (по умолчанию `admin@example.com`
и `password`). Каталог услуг и учётка администратора создаются при первом старте.

Чтобы посмотреть админку не на пустых таблицах, добавьте демо-пользователей
и заявки:

```bash
docker compose exec app php artisan db:seed --class=DemoSeeder
```

Чтобы поговорить с ботом, нужен свой токен от [@BotFather](https://t.me/BotFather) —
см. [Настройка бота и webhook](#настройка-бота-и-webhook). Без токена админка
полностью работоспособна, не работает только отправка сообщений.

## Скриншоты

Пока не сняты. Что и в каком порядке снимать — в
[docs/screenshots/README.md](docs/screenshots/README.md).

## Что умеет

**Бот**

- `/start` — приветствие и главное меню на инлайн-кнопках
- Каталог: категории → услуги → карточка услуги с ценой и описанием
- Оформление заявки в диалоге: имя → телефон (кнопкой «Отправить мой номер» или вручную) → комментарий → подтверждение
- `/orders` — список своих заявок со статусами
- Уведомление клиенту при каждой смене статуса заявки
- `/help` — краткая инструкция

**Админка**

- Дашборд: новые заявки, заявки за сегодня, число пользователей, выручка, последние заявки
- Заявки: вкладки по статусам, фильтры, поиск по номеру, клиенту и телефону
- Смена статуса одной кнопкой — клиент сразу получает сообщение в боте
- Ответ клиенту в Telegram прямо из админки, вся переписка сохраняется
- Карточка пользователя: профиль, его заявки и история сообщений
- Каталог: категории и услуги с сортировкой перетаскиванием и флагом «показывать в боте»
- Рассылки: черновик → запуск через очередь → счётчики доставленных и ошибок в реальном времени
- Пользователи, заблокировавшие бота, помечаются автоматически и исключаются из рассылок

## Стек и почему он

| Технология | Версия | Почему |
|-----------|--------|--------|
| Laravel | 13 | Очереди, миграции, тесты из коробки |
| PHP | 8.4 | Enum-ы с методами, typed properties, named arguments |
| [Nutgram](https://nutgram.dev) | 1.x | Современный фреймворк для ботов: роутинг команд и callback-кнопок с параметрами, встроенные диалоги (Conversations) с сохранением шага, middleware, актуальная поддержка Bot API. Код бота получается компактным — достаточно взглянуть на `routes/telegram.php` |
| [Filament](https://filamentphp.com) | 5.x | Панель с CRUD, фильтрами, действиями и виджетами собирается за часы, а не недели. Построен на Livewire, поэтому кастомные экраны пишутся в той же парадигме |
| MySQL | 8.x | Стандарт для хостингов, где разворачивают такие проекты |
| Очередь | `database` | Работает без Redis на любом дешёвом хостинге. Драйвер меняется одной строкой в `.env` |

Бот работает через **webhook**, а не long polling: Telegram сам присылает
обновления на HTTPS-эндпоинт, сервер не держит постоянное соединение.

## Установка

### Требования

- PHP 8.2+ с расширениями `intl`, `pdo_mysql`, `mbstring`, `curl`, `openssl`, `zip`
- Composer 2
- MySQL 8 или MariaDB 10.6+

### Шаги

```bash
git clone <repo> telegram-bot-admin
cd telegram-bot-admin

composer install
cp .env.example .env
php artisan key:generate
```

Создайте базу и пропишите доступы в `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=telegram_bot_admin
DB_USERNAME=root
DB_PASSWORD=
```

Примените миграции и загрузите демо-каталог:

```bash
php artisan migrate --seed
```

Создайте администратора панели:

```bash
php artisan admin:create
```

Запустите приложение и воркер очереди (рассылки уходят через него):

```bash
php artisan serve
php artisan queue:work
```

Админка: <http://localhost:8000/admin>

### Запуск в OSPanel (Windows)

Положите проект в `C:\OSPanel\home\` — конфиг домена `.osp/project.ini` уже в репозитории,
он указывает Apache на `public` и подключает PHP 8.4 и MySQL 8.4. Перезапустите
Open Server Panel, чтобы она перечитала список проектов, и сайт откроется
на <http://02-telegram-bot-admin/admin> (HTTPS тоже работает — OSPanel сам выпускает
сертификат на домен).

Две детали, на которых легко споткнуться:

- Скорее всего `php` в PATH — это не PHP из OSPanel. Filament требует расширение `intl`,
  поэтому artisan и composer запускайте явно:
  `C:\OSPanel\modules\PHP-8.4\php.exe artisan ...`
- MySQL слушает не на `127.0.0.1`, а на собственном loopback-адресе модуля
  (например `127.0.1.28`). Точный IP показан в окне OSPanel и в `hosts`; его и пишем
  в `DB_HOST`. Либо используйте имя хоста, которое OSPanel прописывает сам: `mysql-8.4`.

### Запуск в Docker

Нужен только `.env` с заполненным `APP_KEY` — остальное поднимется само:

```bash
cp .env.example .env
docker compose run --rm app php artisan key:generate --show   # впишите результат в .env
docker compose up -d
```

Админка: <http://localhost:8080/admin>, вход из `ADMIN_EMAIL` и `ADMIN_PASSWORD`.

Стек состоит из трёх сервисов:

| Сервис | Роль |
|--------|------|
| `app` | FrankenPHP отдаёт `public/` напрямую, без связки nginx + php-fpm. На старте накатывает миграции и сидеры |
| `mysql` | MySQL 8.4, данные в именованном томе. Наружу отдан порт **3307** — 3306 обычно занят локальной базой |
| `queue` | `queue:work`, разбирает рассылки. Без него рассылка навсегда останется в статусе «В очереди» |

Все три роли — один и тот же образ, различаются только аргументом команды
(`serve`, `queue`, `bot`) — его разбирает [docker/entrypoint.sh](docker/entrypoint.sh).

Бот в Docker поднимается отдельным профилем, потому что снаружи webhook до
локального контейнера не достучится и остаётся long polling:

```bash
docker compose --profile bot up -d
```

Artisan-команды выполняются в работающем контейнере:

```bash
docker compose exec app php artisan telegram:webhook info
docker compose exec app php artisan admin:create
docker compose logs -f queue
```

Переменные `compose.yaml` берёт из `.env` проекта, поэтому токен бота в
репозиторий не попадает. `DB_HOST` внутри сети Docker всегда `mysql` — значение
из вашего `.env` перекрывается, локальная база OSPanel не задействована.

## Настройка бота и webhook

### 1. Создать бота

Напишите [@BotFather](https://t.me/BotFather) команду `/newbot`, получите токен
и добавьте его в `.env`:

```dotenv
TELEGRAM_TOKEN=123456789:AAH...            # токен, который выдал BotFather
TELEGRAM_BOT_USERNAME=имя_вашего_бота      # без @, используется только для ссылок
```

Токен хранится только в `.env` — в репозиторий он не попадает.

### 2. Сгенерировать секрет webhook

```bash
php artisan telegram:secret
```

Скопируйте полученную строку в `.env`:

```dotenv
TELEGRAM_WEBHOOK_SECRET=<строка из команды>
```

Telegram присылает этот секрет в заголовке `X-Telegram-Bot-Api-Secret-Token`.
Middleware `VerifyTelegramWebhook` сравнивает его с ожидаемым и отклоняет всё
остальное — публичный URL нельзя обстрелять чужими апдейтами.

### 3. Указать chat_id администраторов

Напишите боту [@userinfobot](https://t.me/userinfobot), чтобы узнать свой `chat_id`,
и перечислите получателей уведомлений о новых заявках через запятую:

```dotenv
TELEGRAM_ADMIN_CHAT_IDS=123456789,987654321
```

### 4. Установить webhook

Telegram требует HTTPS, поэтому локально нужен туннель:

```bash
ngrok http 8000
```

Пропишите выданный адрес в `APP_URL`:

```dotenv
APP_URL=https://1a2b3c4d.ngrok-free.app
```

И установите webhook:

```bash
php artisan telegram:webhook set
php artisan telegram:webhook info     # статус и последняя ошибка
php artisan telegram:webhook remove   # снять webhook
```

На продакшене достаточно `APP_URL=https://ваш-домен` и `php artisan telegram:webhook set`.

### 5. Зарегистрировать меню команд

```bash
php artisan nutgram:register-commands
```

Команды `/start`, `/help`, `/orders` появятся в меню бота рядом с полем ввода.

## Команды проекта

| Команда | Что делает |
|---------|-----------|
| `php artisan admin:create` | Создать администратора веб-панели |
| `php artisan telegram:secret` | Сгенерировать значение `TELEGRAM_WEBHOOK_SECRET` |
| `php artisan telegram:webhook set\|info\|remove` | Управление webhook |
| `php artisan nutgram:register-commands` | Зарегистрировать меню команд бота |
| `php artisan nutgram:list` | Показать все зарегистрированные обработчики |
| `php artisan queue:work` | Воркер очереди: рассылки |
| `php artisan db:seed --class=DemoSeeder` | Демо-пользователи и заявки для скриншотов |
| `php artisan test` | Прогнать тесты |
| `vendor/bin/pint` | Привести код к стилю Laravel |

## Структура

```
app/
├── Console/Commands/        admin:create, telegram:secret, telegram:webhook
├── Enums/                   OrderStatus, BroadcastStatus, MessageDirection
├── Filament/
│   ├── Actions/             Смена статуса, ответ клиенту, запуск рассылки
│   ├── Resources/           Заявки, пользователи, услуги, категории, рассылки
│   └── Widgets/             Статистика и последние заявки на дашборде
├── Http/
│   ├── Controllers/         TelegramWebhookController
│   └── Middleware/          VerifyTelegramWebhook
├── Jobs/                    StartBroadcast, SendBroadcastMessage
├── Models/                  TelegramUser, Service, ServiceCategory, Order, Broadcast, BotMessage
├── Observers/               OrderObserver — уведомление клиента о смене статуса
├── Services/                OrderService, BroadcastService, Telegram\BotMessenger
└── Telegram/
    ├── Commands/            /start
    ├── Conversations/       OrderConversation — диалог оформления заявки
    ├── Handlers/            Меню, каталог, мои заявки
    ├── Middleware/          TrackTelegramUser — регистрация и лог входящих
    └── Support/             Keyboards, Texts, Screen, BotContext

docker/
└── entrypoint.sh            Выбор роли контейнера: serve, queue, bot

routes/
├── telegram.php             Обработчики бота
└── web.php                  Webhook и редирект на админку
```

### Как устроен обмен сообщениями

Все исходящие сообщения проходят через `Telegram\BotMessenger`. Это бесплатно даёт
три вещи: переписка пишется в `bot_messages` и видна в карточке клиента, ответ
`403 Forbidden` помечает пользователя как заблокировавшего бота, а удачная
доставка эту отметку снимает.

Уведомление о смене статуса отправляет `OrderObserver`, а не кнопка в админке.
Поэтому сообщение уходит клиенту и когда статус меняют кнопкой, и когда правят
через форму редактирования, и когда меняют кодом.

Название и цена услуги копируются в заявку в момент оформления. Поменяли прайс
или убрали услугу из каталога — старые заявки остаются такими, какими их видел клиент.

## Тесты

```bash
php artisan test
```

Покрыты:

- команды бота `/start`, `/help`, `/orders` и регистрация пользователя
- каталог: скрытие неактивных категорий и услуг, пустой каталог
- полный сценарий заявки: имя → телефон → комментарий → подтверждение
- валидация телефона и отмена заявки на любом шаге
- фиксация названия и цены услуги в заявке
- webhook: отказ без секрета, с неверным секретом и без настроенного секрета
- уведомление клиента при смене статуса, в том числе при правке через форму
- пометка заблокировавших бота по ответу Telegram
- рассылка: постановка в очередь, исключение заблокированных, задача на получателя, счётчики
- доступность всех экранов админки и карточек

Тесты не ходят в Telegram: пакет подменяет клиент на `Nutgram::fake()`,
исходящие запросы проверяются ассертами.

## Дальнейшее развитие

- **Этап 2.** Оплата в боте: ЮKassa или Telegram Payments
- **Этап 3.** Telegram Web App — мини-приложение с каталогом и корзиной
- **Этап 4.** Аналитика: воронка заказов, конверсия, источники пользователей
- **Этап 5.** Мультиязычность и несколько ботов в одной системе

## Лицензия

MIT
