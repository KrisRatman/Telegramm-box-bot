<?php

namespace App\Telegram\Support;

use App\Models\Bot;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Service;
use App\Models\TelegramUser;
use Closure;

/**
 * Тексты бота собраны в одном месте. Всё, что видит клиент, переводится
 * через lang/{ru,en}/bot.php на текущий язык приложения — его ставит
 * TrackTelegramUser по пользователю апдейта. Уведомления администраторам
 * остаются на русском, как и вся админка.
 */
class Texts
{
    /**
     * Собрать текст на языке клиента. Нужно там, где сообщение уходит
     * не в ответ на апдейт — из админки, очереди или API Mini App.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function for(TelegramUser $user, Closure $callback): mixed
    {
        return self::in($user->preferredLocale(), $callback);
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function in(string $locale, Closure $callback): mixed
    {
        $previous = app()->getLocale();
        app()->setLocale($locale);

        try {
            return $callback();
        } finally {
            app()->setLocale($previous);
        }
    }

    public static function greeting(TelegramUser $user): string
    {
        return __('bot.greeting', ['name' => e($user->full_name)]);
    }

    public static function mainMenu(): string
    {
        return __('bot.main_menu');
    }

    public static function help(Bot $bot): string
    {
        return implode("\n\n", array_filter([
            __('bot.help.title'),
            __('bot.help.steps'),
            $bot->paymentsEnabled() ? __('bot.help.payments') : null,
            __('bot.help.status'),
            __('bot.help.commands'),
        ]));
    }

    public static function emptyCatalog(): string
    {
        return __('bot.catalog.empty');
    }

    public static function categories(): string
    {
        return __('bot.catalog.title');
    }

    public static function emptyCategory(): string
    {
        return __('bot.catalog.empty_category');
    }

    public static function category(string $name, ?string $description): string
    {
        return "<b>{$name}</b>\n\n".($description ?: __('bot.catalog.choose_service'));
    }

    public static function serviceCard(Service $service): string
    {
        $text = '<b>'.$service->translated('name')."</b>\n\n";

        if ($description = $service->translated('description')) {
            $text .= $description."\n\n";
        }

        $text .= __('bot.catalog.price', ['price' => $service->formatted_price]);

        if ($service->duration_minutes) {
            $text .= "\n".__('bot.catalog.duration', ['minutes' => $service->duration_minutes]);
        }

        return $text;
    }

    public static function askName(Service $service): string
    {
        return __('bot.order.ask_name', ['service' => $service->translated('name')]);
    }

    public static function askPhone(): string
    {
        return __('bot.order.ask_phone');
    }

    public static function askComment(): string
    {
        return __('bot.order.ask_comment');
    }

    public static function confirmOrder(Service $service, string $name, string $phone, ?string $comment): string
    {
        $lines = [
            __('bot.order.confirm_title'),
            '',
            __('bot.order.service', ['value' => $service->translated('name')]),
            __('bot.order.price', ['value' => $service->formatted_price]),
            __('bot.order.name', ['value' => e($name)]),
            __('bot.order.phone', ['value' => e($phone)]),
        ];

        if ($comment) {
            $lines[] = __('bot.order.comment', ['value' => e($comment)]);
        }

        return implode("\n", $lines)."\n\n".__('bot.order.confirm_question');
    }

    public static function orderCreated(Order $order): string
    {
        $bot = $order->bot;

        return __('bot.order.created', ['number' => $order->number])."\n\n"
            .self::orderContents($order)
            .__('bot.order.price', ['value' => $order->formatted_price])."\n\n"
            .__('bot.order.created_footer')
            .($bot?->paymentsEnabled() && $order->canBePaid() ? "\n\n".__('bot.order.created_pay') : '');
    }

    /**
     * Одна услуга — строкой, корзина — списком позиций.
     */
    public static function orderContents(Order $order): string
    {
        $items = $order->items;

        if ($items->count() <= 1) {
            return __('bot.order.service', ['value' => $items->isEmpty() ? $order->service_name : self::itemName($items->first())])."\n";
        }

        $text = __('bot.order.contents')."\n";

        foreach ($items as $item) {
            $quantity = $item->quantity > 1 ? " × {$item->quantity}" : '';
            $text .= '• '.self::itemName($item)."{$quantity} — {$item->formatted_total}\n";
        }

        return $text;
    }

    /**
     * На русском — название из заявки (каким его видел клиент),
     * на другом языке — перевод услуги, если он есть.
     */
    public static function itemName(OrderItem $item): string
    {
        if (app()->getLocale() === 'ru' || $item->service === null) {
            return $item->service_name;
        }

        return $item->service->translated('name') ?? $item->service_name;
    }

    public static function orderCancelled(): string
    {
        return __('bot.order.cancelled');
    }

    public static function noOrders(): string
    {
        return __('bot.orders.empty');
    }

    /**
     * @param  iterable<Order>  $orders
     */
    public static function orders(iterable $orders, Bot $bot): string
    {
        $text = __('bot.orders.title')."\n";

        foreach ($orders as $order) {
            $text .= "\n".__('bot.orders.line', ['number' => $order->number, 'status' => self::status($order)])."\n"
                .self::orderTitle($order).", {$order->formatted_price}\n"
                .__('bot.orders.from', ['date' => $order->created_at->format('d.m.Y')])."\n";

            if ($bot->paymentsEnabled() && (float) $order->price > 0) {
                $text .= ($order->isPaid() ? __('bot.orders.paid') : __('bot.orders.unpaid'))."\n";
            }
        }

        return $text;
    }

    /**
     * Краткое название заявки на языке клиента: «Лендинг» или «Лендинг и ещё 2».
     */
    public static function orderTitle(Order $order): string
    {
        $items = $order->items;

        if ($items->isEmpty()) {
            return $order->service_name;
        }

        $first = self::itemName($items->first());

        return $items->count() > 1 ? $first.' '.__('bot.orders.and_more', ['count' => $items->count() - 1]) : $first;
    }

    public static function status(Order $order): string
    {
        return __('bot.status.'.$order->status->value);
    }

    public static function statusChanged(Order $order): string
    {
        return __('bot.orders.status_changed', ['number' => $order->number, 'status' => self::status($order)])."\n\n"
            .__('bot.status_text.'.$order->status->value);
    }

    public static function invoiceUnavailable(): string
    {
        return __('bot.payment.unavailable');
    }

    public static function invoiceFailed(): string
    {
        return __('bot.payment.failed');
    }

    public static function paymentReceived(Payment $payment): string
    {
        return __('bot.payment.received', ['number' => $payment->order->number, 'amount' => $payment->formatted_amount]);
    }

    public static function duplicatePayment(Payment $payment): string
    {
        return __('bot.payment.duplicate', ['number' => $payment->order->number, 'amount' => $payment->formatted_amount]);
    }

    public static function paymentRefunded(Payment $payment): string
    {
        return __('bot.payment.refunded', ['number' => $payment->order->number, 'amount' => $payment->formatted_amount]);
    }

    public static function chooseLanguage(): string
    {
        return __('bot.language.choose');
    }

    public static function languageChanged(): string
    {
        return __('bot.language.changed');
    }

    public static function unknownInput(): string
    {
        return __('bot.unknown_input');
    }

    // --- Администраторам: всегда на русском ---------------------------------

    public static function newOrderForAdmin(Order $order): string
    {
        return self::in('ru', function () use ($order) {
            $user = $order->telegramUser;
            $text = "🔔 <b>Новая заявка №{$order->number}</b>\n"
                ."Бот: {$order->bot?->name}\n\n"
                .self::orderContents($order)
                ."Стоимость: {$order->formatted_price}\n"
                .'Клиент: '.e($order->contact_name)."\n"
                .'Телефон: '.e($order->contact_phone)."\n";

            if ($order->comment) {
                $text .= 'Комментарий: '.e($order->comment)."\n";
            }

            if ($user?->username) {
                $text .= "Telegram: @{$user->username}\n";
            }

            return $text;
        });
    }

    public static function invoiceLog(Order $order): string
    {
        return "💳 Счёт на оплату заявки №{$order->number}: {$order->formatted_price}";
    }

    public static function paymentForAdmin(Payment $payment, bool $isDuplicate = false): string
    {
        $order = $payment->order;
        $title = $isDuplicate
            ? "⚠️ <b>Повторная оплата заявки №{$order->number}</b>\n"
                .'Заявка уже была оплачена — нужен возврат в кабинете ЮKassa.'
            : "💰 <b>Оплачена заявка №{$order->number}</b>";

        return $title."\n\n"
            ."Бот: {$order->bot?->name}\n"
            ."Услуга: {$order->service_name}\n"
            ."Сумма: {$payment->formatted_amount}\n"
            .'Клиент: '.e($order->contact_name)."\n"
            ."ID платежа у провайдера: <code>{$payment->provider_payment_charge_id}</code>";
    }
}
