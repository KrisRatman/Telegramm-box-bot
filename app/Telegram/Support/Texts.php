<?php

namespace App\Telegram\Support;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Service;
use App\Models\TelegramUser;
use App\Services\PaymentService;

/**
 * Тексты бота собраны в одном месте: так их правит контент-менеджер,
 * а не разработчик, и так проще добавить второй язык на этапе 5.
 */
class Texts
{
    public static function greeting(TelegramUser $user): string
    {
        return "Здравствуйте, <b>{$user->full_name}</b>!\n\n"
            ."Я помогу выбрать услугу и оставить заявку — это займёт меньше минуты.\n"
            .'Выберите раздел:';
    }

    public static function mainMenu(): string
    {
        return "<b>Главное меню</b>\n\nВыберите, что вас интересует:";
    }

    public static function help(): string
    {
        return "<b>Как это работает</b>\n\n"
            ."1. Откройте «Каталог услуг» и выберите подходящую.\n"
            ."2. Нажмите «Оставить заявку» и ответьте на три коротких вопроса.\n"
            ."3. Мы свяжемся с вами и подтвердим заявку.\n\n"
            .(PaymentService::enabled()
                ? "Оплатить заявку можно прямо в боте картой — кнопка «Оплатить» появится после оформления.\n\n"
                : '')
            ."Статус заявки всегда виден в разделе «Мои заявки».\n\n"
            .'Команды: /start — главное меню, /help — эта справка, /orders — мои заявки.';
    }

    public static function emptyCatalog(): string
    {
        return 'Каталог пока пуст. Загляните чуть позже — мы уже готовим услуги.';
    }

    public static function categories(): string
    {
        return '<b>Каталог услуг</b>'."\n\n".'Выберите категорию:';
    }

    public static function emptyCategory(): string
    {
        return 'В этой категории пока нет услуг. Выберите другую.';
    }

    public static function serviceCard(Service $service): string
    {
        $text = "<b>{$service->name}</b>\n\n";

        if ($service->description) {
            $text .= $service->description."\n\n";
        }

        $text .= "Стоимость: <b>{$service->formatted_price}</b>";

        if ($service->duration_minutes) {
            $text .= "\nДлительность: {$service->duration_minutes} мин.";
        }

        return $text;
    }

    public static function askName(Service $service): string
    {
        return "Оформляем заявку: <b>{$service->name}</b>\n\nКак к вам обращаться?";
    }

    public static function askPhone(): string
    {
        return 'Оставьте номер телефона — нажмите кнопку ниже или введите номер вручную.';
    }

    public static function askComment(): string
    {
        return 'Добавьте комментарий к заявке (удобное время, пожелания). '
            .'Если добавить нечего — отправьте «-».';
    }

    public static function confirmOrder(Service $service, string $name, string $phone, ?string $comment): string
    {
        $text = "<b>Проверьте заявку</b>\n\n"
            ."Услуга: {$service->name}\n"
            ."Стоимость: {$service->formatted_price}\n"
            ."Имя: {$name}\n"
            ."Телефон: {$phone}\n";

        if ($comment) {
            $text .= "Комментарий: {$comment}\n";
        }

        return $text."\nВсё верно?";
    }

    public static function orderCreated(Order $order): string
    {
        return "✅ Заявка <b>№{$order->number}</b> принята!\n\n"
            ."Услуга: {$order->service_name}\n"
            ."Стоимость: {$order->formatted_price}\n\n"
            .'Мы свяжемся с вами в ближайшее время. Статус можно посмотреть в разделе «Мои заявки».'
            .(PaymentService::enabled() && $order->canBePaid() ? "\n\nОплатить можно сразу — кнопка ниже." : '');
    }

    public static function orderCancelled(): string
    {
        return 'Оформление заявки отменено. Возвращаю вас в главное меню.';
    }

    public static function noOrders(): string
    {
        return 'У вас пока нет заявок. Загляните в каталог — и оформим первую.';
    }

    /**
     * @param  iterable<Order>  $orders
     */
    public static function orders(iterable $orders): string
    {
        $text = "<b>Ваши заявки</b>\n";

        foreach ($orders as $order) {
            $text .= "\n<b>№{$order->number}</b> — {$order->status->getLabel()}\n"
                ."{$order->service_name}, {$order->formatted_price}\n"
                .'от '.$order->created_at->format('d.m.Y')."\n";

            if (PaymentService::enabled() && (float) $order->price > 0) {
                $text .= $order->isPaid() ? "💳 Оплачена\n" : "Не оплачена\n";
            }
        }

        return $text;
    }

    public static function statusChanged(Order $order): string
    {
        return "Заявка <b>№{$order->number}</b> — статус: <b>{$order->status->getLabel()}</b>\n\n"
            .$order->status->notificationText();
    }

    public static function newOrderForAdmin(Order $order): string
    {
        $user = $order->telegramUser;
        $text = "🔔 <b>Новая заявка №{$order->number}</b>\n\n"
            ."Услуга: {$order->service_name}\n"
            ."Стоимость: {$order->formatted_price}\n"
            ."Клиент: {$order->contact_name}\n"
            ."Телефон: {$order->contact_phone}\n";

        if ($order->comment) {
            $text .= "Комментарий: {$order->comment}\n";
        }

        if ($user?->username) {
            $text .= "Telegram: @{$user->username}\n";
        }

        return $text;
    }

    public static function invoiceLog(Order $order): string
    {
        return "💳 Счёт на оплату заявки №{$order->number}: {$order->formatted_price}";
    }

    public static function invoiceUnavailable(): string
    {
        return 'Эту заявку сейчас нельзя оплатить: она уже оплачена или отменена.';
    }

    public static function invoiceFailed(): string
    {
        return 'Не получилось выставить счёт. Попробуйте позже или напишите нам.';
    }

    public static function paymentReceived(Payment $payment): string
    {
        return "✅ Оплата по заявке <b>№{$payment->order->number}</b> получена: {$payment->formatted_amount}.\n\n"
            .'Спасибо! Статус заявки — в разделе «Мои заявки».';
    }

    public static function duplicatePayment(Payment $payment): string
    {
        return "Заявка <b>№{$payment->order->number}</b> уже была оплачена, "
            ."а мы получили ещё один платёж на {$payment->formatted_amount}.\n\n"
            .'Лишние деньги вернём — администратор уже получил уведомление.';
    }

    public static function paymentRefunded(Payment $payment): string
    {
        return "Возврат по заявке <b>№{$payment->order->number}</b> оформлен: {$payment->formatted_amount}.\n\n"
            .'Деньги придут на карту, с которой вы платили. Срок зачисления зависит от банка.';
    }

    public static function paymentForAdmin(Payment $payment, bool $isDuplicate = false): string
    {
        $order = $payment->order;
        $title = $isDuplicate
            ? "⚠️ <b>Повторная оплата заявки №{$order->number}</b>\n"
                .'Заявка уже была оплачена — нужен возврат в кабинете ЮKassa.'
            : "💰 <b>Оплачена заявка №{$order->number}</b>";

        return $title."\n\n"
            ."Услуга: {$order->service_name}\n"
            ."Сумма: {$payment->formatted_amount}\n"
            ."Клиент: {$order->contact_name}\n"
            ."ID платежа у провайдера: <code>{$payment->provider_payment_charge_id}</code>";
    }

    public static function unknownInput(): string
    {
        return 'Не понял сообщение. Откройте главное меню командой /start.';
    }
}
