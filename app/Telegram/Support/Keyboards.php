<?php

namespace App\Telegram\Support;

use App\Models\Bot;
use App\Models\Order;
use App\Models\Service;
use App\Models\ServiceCategory;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;
use SergiX44\Nutgram\Telegram\Types\Keyboard\KeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\ReplyKeyboardMarkup;
use SergiX44\Nutgram\Telegram\Types\Keyboard\ReplyKeyboardRemove;
use SergiX44\Nutgram\Telegram\Types\WebApp\WebAppInfo;

/**
 * Инлайн-клавиатуры бота. Схема callback_data: "раздел:действие:параметр".
 * Подписи переводятся на текущий язык, как и тексты.
 */
class Keyboards
{
    /** Языки для /language: код => подпись на самом языке. */
    public const LANGUAGES = [
        'ru' => '🇷🇺 Русский',
        'en' => '🇬🇧 English',
    ];

    public static function mainMenu(Bot $bot): InlineKeyboardMarkup
    {
        $keyboard = InlineKeyboardMarkup::make();

        if ($url = $bot->miniAppUrl()) {
            $keyboard->addRow(InlineKeyboardButton::make(__('bot.buttons.mini_app'), web_app: WebAppInfo::make($url)));
        }

        return $keyboard
            ->addRow(InlineKeyboardButton::make(__('bot.buttons.catalog'), callback_data: 'catalog:list'))
            ->addRow(InlineKeyboardButton::make(__('bot.buttons.my_orders'), callback_data: 'orders:my'))
            ->addRow(
                InlineKeyboardButton::make(__('bot.buttons.about'), callback_data: 'menu:help'),
                InlineKeyboardButton::make(__('bot.buttons.language'), callback_data: 'lang:choose'),
            );
    }

    public static function languages(): InlineKeyboardMarkup
    {
        $keyboard = InlineKeyboardMarkup::make();

        foreach (self::LANGUAGES as $code => $label) {
            $keyboard->addRow(InlineKeyboardButton::make($label, callback_data: "lang:set:{$code}"));
        }

        return $keyboard;
    }

    /**
     * @param  iterable<ServiceCategory>  $categories
     */
    public static function categories(iterable $categories): InlineKeyboardMarkup
    {
        $keyboard = InlineKeyboardMarkup::make();

        foreach ($categories as $category) {
            $keyboard->addRow(InlineKeyboardButton::make(
                $category->translated('name'),
                callback_data: "catalog:category:{$category->id}",
            ));
        }

        return $keyboard->addRow(self::backButton('menu:main'));
    }

    /**
     * @param  iterable<Service>  $services
     */
    public static function services(iterable $services): InlineKeyboardMarkup
    {
        $keyboard = InlineKeyboardMarkup::make();

        foreach ($services as $service) {
            $keyboard->addRow(InlineKeyboardButton::make(
                $service->translated('name')." — {$service->formatted_price}",
                callback_data: "catalog:service:{$service->id}",
            ));
        }

        return $keyboard->addRow(self::backButton('catalog:list'));
    }

    public static function serviceCard(Service $service): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(InlineKeyboardButton::make(__('bot.buttons.order'), callback_data: "order:create:{$service->id}"))
            ->addRow(self::backButton("catalog:category:{$service->service_category_id}"));
    }

    /**
     * Экран после оформления: сразу предлагаем оплатить.
     */
    public static function orderCreated(Order $order): InlineKeyboardMarkup
    {
        $keyboard = InlineKeyboardMarkup::make();

        if ($order->bot?->paymentsEnabled() && $order->canBePaid()) {
            $keyboard->addRow(self::payButton($order, __('bot.buttons.pay_amount', ['amount' => $order->formatted_price])));
        }

        return $keyboard->addRow(self::backButton('menu:main'));
    }

    /**
     * «Мои заявки»: кнопка оплаты у каждой неоплаченной заявки.
     *
     * @param  iterable<Order>  $orders
     */
    public static function myOrders(iterable $orders, Bot $bot): InlineKeyboardMarkup
    {
        $keyboard = InlineKeyboardMarkup::make();

        if ($bot->paymentsEnabled()) {
            foreach ($orders as $order) {
                if ($order->canBePaid()) {
                    $keyboard->addRow(self::payButton($order, __('bot.buttons.pay_number', ['number' => $order->number])));
                }
            }
        }

        return $keyboard->addRow(self::backButton('menu:main'));
    }

    public static function backToMenu(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()->addRow(self::backButton('menu:main'));
    }

    public static function cancelOrder(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(InlineKeyboardButton::make(__('bot.buttons.cancel_order'), callback_data: 'order:cancel'));
    }

    public static function confirmOrder(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(InlineKeyboardButton::make(__('bot.buttons.confirm'), callback_data: 'order:confirm'))
            ->addRow(InlineKeyboardButton::make(__('bot.buttons.cancel'), callback_data: 'order:cancel'));
    }

    /**
     * Запрос телефона. Инлайн-кнопки просить контакт не умеют,
     * поэтому здесь обычная reply-клавиатура.
     */
    public static function requestPhone(): ReplyKeyboardMarkup
    {
        return ReplyKeyboardMarkup::make(resize_keyboard: true, one_time_keyboard: true)
            ->addRow(KeyboardButton::make(__('bot.buttons.share_phone'), request_contact: true));
    }

    public static function removeReplyKeyboard(): ReplyKeyboardRemove
    {
        return ReplyKeyboardRemove::make(true);
    }

    public static function payButton(Order $order, string $label): InlineKeyboardButton
    {
        return InlineKeyboardButton::make($label, callback_data: "payment:order:{$order->id}");
    }

    public static function backButton(string $callbackData): InlineKeyboardButton
    {
        return InlineKeyboardButton::make(__('bot.buttons.back'), callback_data: $callbackData);
    }
}
