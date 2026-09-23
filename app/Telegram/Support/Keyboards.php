<?php

namespace App\Telegram\Support;

use App\Models\Order;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Services\PaymentService;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;
use SergiX44\Nutgram\Telegram\Types\Keyboard\KeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\ReplyKeyboardMarkup;
use SergiX44\Nutgram\Telegram\Types\Keyboard\ReplyKeyboardRemove;

/**
 * Инлайн-клавиатуры бота. Схема callback_data: "раздел:действие:параметр".
 */
class Keyboards
{
    public static function mainMenu(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(InlineKeyboardButton::make('🛍 Каталог услуг', callback_data: 'catalog:list'))
            ->addRow(InlineKeyboardButton::make('📋 Мои заявки', callback_data: 'orders:my'))
            ->addRow(InlineKeyboardButton::make('ℹ️ О нас и контакты', callback_data: 'menu:help'));
    }

    /**
     * @param  iterable<ServiceCategory>  $categories
     */
    public static function categories(iterable $categories): InlineKeyboardMarkup
    {
        $keyboard = InlineKeyboardMarkup::make();

        foreach ($categories as $category) {
            $keyboard->addRow(InlineKeyboardButton::make(
                $category->name,
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
                "{$service->name} — {$service->formatted_price}",
                callback_data: "catalog:service:{$service->id}",
            ));
        }

        return $keyboard->addRow(self::backButton('catalog:list'));
    }

    public static function serviceCard(Service $service): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(InlineKeyboardButton::make(
                '✅ Оставить заявку',
                callback_data: "order:create:{$service->id}",
            ))
            ->addRow(self::backButton("catalog:category:{$service->service_category_id}"));
    }

    /**
     * Экран после оформления: сразу предлагаем оплатить.
     */
    public static function orderCreated(Order $order): InlineKeyboardMarkup
    {
        $keyboard = InlineKeyboardMarkup::make();

        if (PaymentService::enabled() && $order->canBePaid()) {
            $keyboard->addRow(self::payButton($order, "💳 Оплатить {$order->formatted_price}"));
        }

        return $keyboard->addRow(self::backButton('menu:main'));
    }

    /**
     * «Мои заявки»: кнопка оплаты у каждой неоплаченной заявки.
     *
     * @param  iterable<Order>  $orders
     */
    public static function myOrders(iterable $orders): InlineKeyboardMarkup
    {
        $keyboard = InlineKeyboardMarkup::make();

        if (PaymentService::enabled()) {
            foreach ($orders as $order) {
                if ($order->canBePaid()) {
                    $keyboard->addRow(self::payButton($order, "💳 Оплатить №{$order->number}"));
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
            ->addRow(InlineKeyboardButton::make('✖️ Отменить заявку', callback_data: 'order:cancel'));
    }

    public static function confirmOrder(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(InlineKeyboardButton::make('✅ Подтвердить', callback_data: 'order:confirm'))
            ->addRow(InlineKeyboardButton::make('✖️ Отменить', callback_data: 'order:cancel'));
    }

    /**
     * Запрос телефона. Инлайн-кнопки просить контакт не умеют,
     * поэтому здесь обычная reply-клавиатура.
     */
    public static function requestPhone(): ReplyKeyboardMarkup
    {
        return ReplyKeyboardMarkup::make(resize_keyboard: true, one_time_keyboard: true)
            ->addRow(KeyboardButton::make('📱 Отправить мой номер', request_contact: true));
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
        return InlineKeyboardButton::make('⬅️ Назад', callback_data: $callbackData);
    }
}
