<?php

/** @var Nutgram $bot */

use App\Telegram\Commands\StartCommand;
use App\Telegram\Conversations\OrderConversation;
use App\Telegram\Handlers\CatalogHandler;
use App\Telegram\Handlers\LanguageHandler;
use App\Telegram\Handlers\MenuHandler;
use App\Telegram\Handlers\OrdersHandler;
use App\Telegram\Handlers\PaymentHandler;
use App\Telegram\Middleware\TrackTelegramUser;
use App\Telegram\Support\Texts;
use SergiX44\Nutgram\Nutgram;

/*
|--------------------------------------------------------------------------
| Обработчики Telegram-бота
|--------------------------------------------------------------------------
| callback_data устроена как "раздел:действие:параметр" — так обработчики
| читаются как обычные маршруты.
*/

$bot->middleware(TrackTelegramUser::class);

// --- Команды ---------------------------------------------------------------

$bot->onCommand('start', StartCommand::class)
    ->description('Главное меню');

$bot->onCommand('help', [MenuHandler::class, 'help'])
    ->description('Как оформить заявку');

$bot->onCommand('orders', [OrdersHandler::class, 'my'])
    ->description('Мои заявки');

$bot->onCommand('language', [LanguageHandler::class, 'choose'])
    ->description('Сменить язык');

// --- Главное меню ----------------------------------------------------------

$bot->onCallbackQueryData('menu:main', [MenuHandler::class, 'main']);
$bot->onCallbackQueryData('menu:help', [MenuHandler::class, 'help']);

// --- Язык ------------------------------------------------------------------

$bot->onCallbackQueryData('lang:choose', [LanguageHandler::class, 'choose']);
$bot->onCallbackQueryData('lang:set:{locale}', [LanguageHandler::class, 'set']);

// --- Каталог ---------------------------------------------------------------

$bot->onCallbackQueryData('catalog:list', [CatalogHandler::class, 'categories']);
$bot->onCallbackQueryData('catalog:category:{categoryId}', [CatalogHandler::class, 'services']);
$bot->onCallbackQueryData('catalog:service:{serviceId}', [CatalogHandler::class, 'card']);

// --- Заявки ----------------------------------------------------------------

$bot->onCallbackQueryData('orders:my', [OrdersHandler::class, 'my']);

$bot->onCallbackQueryData('order:create:{serviceId}', function (Nutgram $bot, string $serviceId) {
    OrderConversation::begin($bot, data: ['serviceId' => (int) $serviceId]);
});

// --- Оплата ----------------------------------------------------------------

$bot->onCallbackQueryData('payment:order:{orderId}', [PaymentHandler::class, 'pay']);

$bot->onPreCheckoutQuery([PaymentHandler::class, 'preCheckout']);

// Списание нельзя потерять: если клиент в этот момент оформляет новую заявку,
// без willStopConversations апдейт ушёл бы в шаг диалога вместо обработчика.
$bot->onSuccessfulPayment([PaymentHandler::class, 'successful'])
    ->willStopConversations();

// --- Фолбэк ----------------------------------------------------------------

$bot->fallback(function (Nutgram $bot) {
    $bot->sendMessage(Texts::unknownInput());
});
