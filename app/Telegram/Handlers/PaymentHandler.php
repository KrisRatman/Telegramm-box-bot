<?php

namespace App\Telegram\Handlers;

use App\Services\PaymentService;
use App\Telegram\Support\BotContext;
use App\Telegram\Support\Texts;
use SergiX44\Nutgram\Nutgram;

class PaymentHandler
{
    public function __construct(private readonly PaymentService $payments) {}

    /**
     * Кнопка «Оплатить»: присылаем счёт отдельным сообщением.
     */
    public function pay(Nutgram $bot, string $orderId): void
    {
        // Ищем только среди заявок самого пользователя: id в callback_data
        // можно подменить, а чужой счёт выставлять нельзя.
        $order = BotContext::user($bot)->orders()->find((int) $orderId);

        if ($order === null || ! PaymentService::enabled() || ! $order->canBePaid()) {
            $bot->answerCallbackQuery(text: Texts::invoiceUnavailable(), show_alert: true);

            return;
        }

        if (! $this->payments->sendInvoice($order)) {
            $bot->answerCallbackQuery(text: Texts::invoiceFailed(), show_alert: true);

            return;
        }

        $bot->answerCallbackQuery();
    }

    /**
     * Последняя проверка перед списанием. Telegram ждёт ответ не дольше
     * 10 секунд — здесь только чтение из БД, без внешних запросов.
     */
    public function preCheckout(Nutgram $bot): void
    {
        $query = $bot->preCheckoutQuery();

        if ($query === null) {
            return;
        }

        $error = $this->payments->validatePreCheckout($query);

        $bot->answerPreCheckoutQuery(
            ok: $error === null,
            pre_checkout_query_id: $query->id,
            error_message: $error,
        );
    }

    /**
     * Деньги списаны. Клиенту и админам пишет PaymentService.
     */
    public function successful(Nutgram $bot): void
    {
        $paid = $bot->message()?->successful_payment;

        if ($paid !== null) {
            $this->payments->confirm($paid);
        }
    }
}
