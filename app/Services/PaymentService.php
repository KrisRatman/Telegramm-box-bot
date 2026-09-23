<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Telegram\BotMessenger;
use App\Telegram\Support\Texts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Telegram\Types\Payment\PreCheckoutQuery;
use SergiX44\Nutgram\Telegram\Types\Payment\SuccessfulPayment;

/**
 * Оплата через Telegram Payments. Путь денег:
 *
 * 1. sendInvoice()          — бот присылает клиенту счёт, в payload лежит ключ платежа;
 * 2. validatePreCheckout()  — перед списанием Telegram спрашивает, можно ли принять оплату;
 *                             ответить нужно за 10 секунд, иначе платёж отменится;
 * 3. confirm()              — деньги списаны, приходит successful_payment.
 *
 * Возврат делается в личном кабинете провайдера (ЮKassa), Bot API
 * возвращать деньги за карточные платежи не умеет — markRefunded()
 * только фиксирует факт и сообщает клиенту.
 */
class PaymentService
{
    public function __construct(private readonly BotMessenger $messenger) {}

    public static function enabled(): bool
    {
        return filled(config('telegram.payments.provider_token'));
    }

    /**
     * Выставляет счёт по заявке. Повторный вызов не плодит платежи:
     * используется тот же ожидающий платёж, сумма подтягивается из заявки.
     *
     * @return bool Доставлен ли счёт клиенту.
     */
    public function sendInvoice(Order $order, ?User $author = null): bool
    {
        if (! self::enabled() || ! $order->canBePaid()) {
            return false;
        }

        $order->loadMissing('telegramUser');

        if ($order->telegramUser === null) {
            return false;
        }

        $payment = $this->pendingPaymentFor($order);

        return $this->messenger->sendInvoice(
            user: $order->telegramUser,
            invoice: $this->invoiceFor($order, $payment),
            logText: Texts::invoiceLog($order),
            author: $author,
        );
    }

    /**
     * @return string|null Текст отказа для клиента или null, если платёж можно принимать.
     */
    public function validatePreCheckout(PreCheckoutQuery $query): ?string
    {
        $payment = Payment::query()
            ->with('order')
            ->where('invoice_payload', $query->invoice_payload)
            ->first();

        if ($payment === null || $payment->order === null) {
            return 'Счёт не найден. Откройте «Мои заявки» и запросите новый.';
        }

        $order = $payment->order;

        if ($order->status === OrderStatus::Cancelled) {
            return 'Заявка отменена — оплата не требуется.';
        }

        if ($payment->status !== PaymentStatus::Pending || $order->isPaid()) {
            return 'Эта заявка уже оплачена.';
        }

        // Старый счёт в чате мог остаться после того, как админ поменял цену.
        if ($query->currency !== $payment->currency
            || $query->total_amount !== $payment->amountInMinorUnits()
            || $payment->amountInMinorUnits() !== Payment::toMinorUnits($order->price)) {
            return 'Сумма заявки изменилась. Запросите новый счёт в разделе «Мои заявки».';
        }

        return null;
    }

    /**
     * Фиксирует списание. Telegram может прислать один апдейт дважды,
     * поэтому повтор по тому же charge_id просто игнорируется.
     */
    public function confirm(SuccessfulPayment $paid): ?Payment
    {
        [$payment, $isNew, $isDuplicate] = DB::transaction(function () use ($paid) {
            $alreadyStored = Payment::query()
                ->where('telegram_payment_charge_id', $paid->telegram_payment_charge_id)
                ->first();

            if ($alreadyStored !== null) {
                return [$alreadyStored, false, false];
            }

            $payment = Payment::query()
                ->where('invoice_payload', $paid->invoice_payload)
                ->lockForUpdate()
                ->first();

            if ($payment === null) {
                return [null, false, false];
            }

            // Два нажатия «Оплатить» на одном счёте могут пройти pre-checkout
            // одновременно. Второе списание сохраняем отдельно — его вернут.
            $isDuplicate = $payment->status !== PaymentStatus::Pending;

            if ($isDuplicate) {
                $payment = new Payment(['order_id' => $payment->order_id]);
            }

            $payment->fill([
                'status' => PaymentStatus::Paid,
                // Фиксируем то, что реально списано, а не то, что ждали.
                'amount' => $paid->total_amount / 100,
                'currency' => $paid->currency,
                'telegram_payment_charge_id' => $paid->telegram_payment_charge_id,
                'provider_payment_charge_id' => $paid->provider_payment_charge_id,
                'paid_at' => now(),
            ])->save();

            $order = $payment->order()->lockForUpdate()->first();

            if ($order !== null && ! $order->isPaid()) {
                $order->forceFill(['paid_at' => $payment->paid_at])->save();
            }

            return [$payment, true, $isDuplicate];
        });

        if ($payment === null) {
            // Деньги списаны, а платежа у нас нет — такое разбирают руками.
            Log::critical('Оплата по неизвестному счёту', [
                'invoice_payload' => $paid->invoice_payload,
                'telegram_payment_charge_id' => $paid->telegram_payment_charge_id,
                'provider_payment_charge_id' => $paid->provider_payment_charge_id,
                'total_amount' => $paid->total_amount,
            ]);

            return null;
        }

        if ($isNew) {
            $this->notifyPaid($payment, $isDuplicate);
        }

        return $payment;
    }

    /**
     * Отмечает возврат, который администратор уже сделал в кабинете провайдера.
     */
    public function markRefunded(Payment $payment): Payment
    {
        if ($payment->status !== PaymentStatus::Paid) {
            return $payment;
        }

        DB::transaction(function () use ($payment) {
            $payment->forceFill([
                'status' => PaymentStatus::Refunded,
                'refunded_at' => now(),
            ])->save();

            $order = $payment->order;
            $stillPaid = $order->payments()->where('status', PaymentStatus::Paid)->exists();

            if (! $stillPaid) {
                $order->forceFill(['paid_at' => null])->save();
            }
        });

        $payment->loadMissing('order.telegramUser');

        if ($payment->order->telegramUser !== null) {
            $this->messenger->sendToUser($payment->order->telegramUser, Texts::paymentRefunded($payment));
        }

        return $payment;
    }

    private function pendingPaymentFor(Order $order): Payment
    {
        $payment = $order->payments()
            ->where('status', PaymentStatus::Pending)
            ->latest('id')
            ->first();

        $currency = config('telegram.payments.currency', 'RUB');

        if ($payment === null) {
            return $order->payments()->create([
                'status' => PaymentStatus::Pending,
                'amount' => $order->price,
                'currency' => $currency,
            ]);
        }

        $payment->fill(['amount' => $order->price, 'currency' => $currency]);

        if ($payment->isDirty()) {
            $payment->save();
        }

        return $payment;
    }

    /**
     * @return array<string, mixed>
     */
    private function invoiceFor(Order $order, Payment $payment): array
    {
        $invoice = [
            // Ограничения Bot API: заголовок до 32 символов, описание до 255.
            'title' => mb_substr("Заявка №{$order->number}", 0, 32),
            'description' => mb_substr("Оплата услуги «{$order->service_name}»", 0, 255),
            'payload' => $payment->invoice_payload,
            'provider_token' => (string) config('telegram.payments.provider_token'),
            'currency' => $payment->currency,
            'prices' => [
                ['label' => mb_substr($order->service_name, 0, 64), 'amount' => $payment->amountInMinorUnits()],
            ],
        ];

        if (config('telegram.payments.receipt.enabled')) {
            // ЮKassa отправляет чек на телефон покупателя, поэтому просим его
            // в форме оплаты и передаём провайдеру вместе с позицией чека.
            $invoice['need_phone_number'] = true;
            $invoice['send_phone_number_to_provider'] = true;
            $invoice['provider_data'] = json_encode([
                'receipt' => [
                    'items' => [[
                        'description' => mb_substr($order->service_name, 0, 128),
                        'quantity' => '1.00',
                        'amount' => [
                            'value' => number_format((float) $payment->amount, 2, '.', ''),
                            'currency' => $payment->currency,
                        ],
                        'vat_code' => (int) config('telegram.payments.receipt.vat_code', 1),
                        'payment_mode' => 'full_payment',
                        'payment_subject' => 'service',
                    ]],
                ],
            ], JSON_UNESCAPED_UNICODE);
        }

        return $invoice;
    }

    private function notifyPaid(Payment $payment, bool $isDuplicate): void
    {
        $payment->loadMissing('order.telegramUser');
        $order = $payment->order;

        if ($order->telegramUser !== null) {
            $this->messenger->sendToUser(
                $order->telegramUser,
                $isDuplicate ? Texts::duplicatePayment($payment) : Texts::paymentReceived($payment),
            );
        }

        $this->messenger->notifyAdmins(Texts::paymentForAdmin($payment, $isDuplicate));
    }
}
