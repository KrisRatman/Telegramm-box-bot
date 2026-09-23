<?php

namespace Tests\Feature\Telegram;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Service;
use App\Models\TelegramUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\UpdateType;
use Tests\Concerns\InteractsWithBot;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use InteractsWithBot, RefreshDatabase;

    private const CHAT_ID = 424242;

    private const ADMIN_CHAT_ID = 111;

    private Nutgram $bot;

    private TelegramUser $user;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'telegram.payments.provider_token' => 'test-provider-token',
            'telegram.admin_chat_ids' => [(string) self::ADMIN_CHAT_ID],
        ]);

        $this->bot = $this->fakeBot(self::CHAT_ID);
        $this->user = TelegramUser::factory()->create(['chat_id' => self::CHAT_ID]);
    }

    // --- Счёт ---------------------------------------------------------------

    public function test_order_confirmation_offers_payment(): void
    {
        $service = Service::factory()->create(['price' => 25000]);

        $this->bot->hearCallbackQueryData("order:create:{$service->id}")->reply();
        $this->bot->hearText('Иван')->reply();
        $this->bot->hearText('+79001234567')->reply();
        $this->bot->hearText('-')->reply();
        $this->bot->hearCallbackQueryData('order:confirm')->reply();

        $buttons = collect($this->requestsTo($this->bot, 'editMessageText'))
            ->flatMap(fn (array $payload) => $this->buttonLabels($payload))
            ->all();

        $this->assertContains('💳 Оплатить 25 000 ₽', $buttons);
    }

    public function test_payment_buttons_are_hidden_without_provider_token(): void
    {
        config(['telegram.payments.provider_token' => null]);
        Order::factory()->for($this->user)->create();

        $this->bot->hearCallbackQueryData('orders:my')->reply();

        $buttons = $this->buttonLabels($this->requestsTo($this->bot, 'editMessageText')[0]);

        $this->assertSame(['⬅️ Назад'], $buttons);
    }

    public function test_my_orders_show_pay_button_only_for_unpaid_orders(): void
    {
        $unpaid = Order::factory()->for($this->user)->create();
        $paid = Order::factory()->for($this->user)->create(['paid_at' => now()]);
        $cancelled = Order::factory()->for($this->user)->status(OrderStatus::Cancelled)->create();

        $this->bot->hearCallbackQueryData('orders:my')->reply();

        $payload = $this->requestsTo($this->bot, 'editMessageText')[0];
        $buttons = $this->buttonLabels($payload);

        $this->assertContains("💳 Оплатить №{$unpaid->number}", $buttons);
        $this->assertNotContains("💳 Оплатить №{$paid->number}", $buttons);
        $this->assertNotContains("💳 Оплатить №{$cancelled->number}", $buttons);
        $this->assertStringContainsString('💳 Оплачена', $payload['text']);
    }

    public function test_pay_button_sends_invoice(): void
    {
        $order = Order::factory()->for($this->user)->create(['price' => 1500]);

        $this->bot->hearCallbackQueryData("payment:order:{$order->id}")->reply();

        $invoices = $this->requestsTo($this->bot, 'sendInvoice');
        $this->assertCount(1, $invoices);

        $payment = Payment::query()->sole();
        $invoice = $invoices[0];

        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame($payment->invoice_payload, $invoice['payload']);
        $this->assertSame('test-provider-token', $invoice['provider_token']);
        $this->assertSame('RUB', $invoice['currency']);
        $this->assertSame(150000, $this->prices($invoice)[0]['amount']);
        $this->assertArrayNotHasKey('provider_data', $invoice);

        $this->assertDatabaseHas('bot_messages', [
            'telegram_user_id' => $this->user->id,
            'text' => "💳 Счёт на оплату заявки №{$order->number}: 1 500 ₽",
        ]);
    }

    public function test_repeated_invoice_reuses_pending_payment(): void
    {
        $order = Order::factory()->for($this->user)->create(['price' => 1500]);

        $this->bot->hearCallbackQueryData("payment:order:{$order->id}")->reply();

        // Админ уточнил цену — новый счёт выставляется уже на новую сумму.
        $order->update(['price' => 2000]);
        $this->bot->hearCallbackQueryData("payment:order:{$order->id}")->reply();

        $payment = Payment::query()->sole();

        $this->assertSame('2000.00', $payment->amount);
        $this->assertSame(200000, $this->prices($this->requestsTo($this->bot, 'sendInvoice')[0])[0]['amount']);
    }

    public function test_cannot_pay_for_someone_elses_order(): void
    {
        $foreign = Order::factory()->create();

        $this->bot->hearCallbackQueryData("payment:order:{$foreign->id}")->reply();

        $this->assertSame([], $this->requestsTo($this->bot, 'sendInvoice'));
        $this->assertSame(0, Payment::query()->count());
        $this->assertTrue($this->requestsTo($this->bot, 'answerCallbackQuery')[0]['show_alert']);
    }

    public function test_receipt_data_is_sent_when_enabled(): void
    {
        config(['telegram.payments.receipt.enabled' => true, 'telegram.payments.receipt.vat_code' => 4]);
        $order = Order::factory()->for($this->user)->create(['price' => 1500, 'service_name' => 'Лендинг']);

        $this->bot->hearCallbackQueryData("payment:order:{$order->id}")->reply();

        $invoice = $this->requestsTo($this->bot, 'sendInvoice')[0];
        $item = json_decode($invoice['provider_data'], true)['receipt']['items'][0];

        $this->assertTrue($invoice['need_phone_number']);
        $this->assertTrue($invoice['send_phone_number_to_provider']);
        $this->assertSame('Лендинг', $item['description']);
        $this->assertSame(['value' => '1500.00', 'currency' => 'RUB'], $item['amount']);
        $this->assertSame(4, $item['vat_code']);
    }

    // --- Pre-checkout -------------------------------------------------------

    public function test_pre_checkout_accepts_valid_payment(): void
    {
        $payment = $this->pendingPayment(price: 1500);

        $this->preCheckout($payment, 150000);

        $answer = $this->requestsTo($this->bot, 'answerPreCheckoutQuery')[0];

        $this->assertTrue($answer['ok']);
        $this->assertArrayNotHasKey('error_message', $answer);
    }

    public function test_pre_checkout_rejects_cancelled_order(): void
    {
        $payment = $this->pendingPayment(price: 1500);
        $payment->order->updateQuietly(['status' => OrderStatus::Cancelled]);

        $this->preCheckout($payment, 150000);

        $this->assertPreCheckoutRejected('отменена');
    }

    public function test_pre_checkout_rejects_already_paid_order(): void
    {
        $payment = $this->pendingPayment(price: 1500);
        $payment->order->update(['paid_at' => now()]);

        $this->preCheckout($payment, 150000);

        $this->assertPreCheckoutRejected('уже оплачена');
    }

    public function test_pre_checkout_rejects_stale_invoice_after_price_change(): void
    {
        $payment = $this->pendingPayment(price: 1500);
        $payment->order->update(['price' => 3000]);

        $this->preCheckout($payment, 150000);

        $this->assertPreCheckoutRejected('Сумма заявки изменилась');
    }

    public function test_pre_checkout_rejects_unknown_payload(): void
    {
        $this->bot->hearUpdateType(UpdateType::PRE_CHECKOUT_QUERY, [
            'id' => 'query-1',
            'from' => ['id' => self::CHAT_ID],
            'currency' => 'RUB',
            'total_amount' => 150000,
            'invoice_payload' => 'pay_unknown',
        ])->reply();

        $this->assertPreCheckoutRejected('Счёт не найден');
    }

    // --- Успешная оплата ----------------------------------------------------

    public function test_successful_payment_marks_order_paid_and_notifies(): void
    {
        $payment = $this->pendingPayment(price: 1500);

        $this->successfulPayment($payment, 'charge-1');

        $payment->refresh();

        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertSame('charge-1', $payment->telegram_payment_charge_id);
        $this->assertSame('provider-charge-1', $payment->provider_payment_charge_id);
        $this->assertNotNull($payment->paid_at);
        $this->assertTrue($payment->order->fresh()->isPaid());

        $messages = collect($this->requestsTo($this->bot, 'sendMessage'));

        $this->assertTrue($messages->contains(
            fn (array $m) => $m['chat_id'] === self::CHAT_ID && str_contains($m['text'], 'получена')
        ), 'Клиент не получил подтверждение оплаты.');
        $this->assertTrue($messages->contains(
            fn (array $m) => $m['chat_id'] === self::ADMIN_CHAT_ID && str_contains($m['text'], 'Оплачена заявка')
        ), 'Администратор не получил уведомление об оплате.');
    }

    public function test_repeated_update_is_processed_once(): void
    {
        $payment = $this->pendingPayment(price: 1500);

        $this->successfulPayment($payment, 'charge-1');
        $this->successfulPayment($payment, 'charge-1');

        $this->assertSame(1, Payment::query()->count());
        $this->assertSame([], $this->requestsTo($this->bot, 'sendMessage'));
    }

    public function test_second_charge_for_same_invoice_is_stored_separately(): void
    {
        $payment = $this->pendingPayment(price: 1500);

        $this->successfulPayment($payment, 'charge-1');
        $this->successfulPayment($payment, 'charge-2');

        $this->assertSame(2, Payment::query()->where('status', PaymentStatus::Paid)->count());

        $adminMessage = collect($this->requestsTo($this->bot, 'sendMessage'))
            ->firstWhere('chat_id', self::ADMIN_CHAT_ID);

        $this->assertStringContainsString('Повторная оплата', $adminMessage['text']);
    }

    public function test_payment_is_not_lost_during_active_conversation(): void
    {
        $payment = $this->pendingPayment(price: 1500);
        $service = Service::factory()->create();

        // Клиент начал оформлять новую заявку и в этот момент оплатил старую.
        $this->bot->willStartConversation();
        $this->bot->hearCallbackQueryData("order:create:{$service->id}")->reply();
        $this->bot->assertActiveConversation(self::CHAT_ID, self::CHAT_ID);

        $this->successfulPayment($payment, 'charge-1');

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
    }

    // --- Хелперы ------------------------------------------------------------

    private function pendingPayment(int $price): Payment
    {
        $order = Order::factory()->for($this->user)->create(['price' => $price]);

        return Payment::factory()->forOrder($order)->create();
    }

    private function preCheckout(Payment $payment, int $totalAmount): void
    {
        $this->bot->hearUpdateType(UpdateType::PRE_CHECKOUT_QUERY, [
            'id' => 'query-1',
            'from' => ['id' => self::CHAT_ID],
            'currency' => 'RUB',
            'total_amount' => $totalAmount,
            'invoice_payload' => $payment->invoice_payload,
        ])->reply();
    }

    private function successfulPayment(Payment $payment, string $chargeId): void
    {
        $this->bot->hearMessage([
            'successful_payment' => [
                'currency' => 'RUB',
                'total_amount' => $payment->amountInMinorUnits(),
                'invoice_payload' => $payment->invoice_payload,
                'telegram_payment_charge_id' => $chargeId,
                'provider_payment_charge_id' => 'provider-'.$chargeId,
            ],
        ])->reply();
    }

    private function assertPreCheckoutRejected(string $reason): void
    {
        $answer = $this->requestsTo($this->bot, 'answerPreCheckoutQuery')[0];

        $this->assertFalse($answer['ok']);
        $this->assertStringContainsString($reason, $answer['error_message']);
    }

    /**
     * @param  array<string, mixed>  $invoice
     * @return array<int, array{label: string, amount: int}>
     */
    private function prices(array $invoice): array
    {
        $prices = $invoice['prices'];

        return is_string($prices) ? json_decode($prices, true) : $prices;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    private function buttonLabels(array $payload): array
    {
        $markup = $payload['reply_markup'] ?? [];

        if (is_string($markup)) {
            $markup = json_decode($markup, true) ?: [];
        }

        return collect($markup['inline_keyboard'] ?? [])->flatten(1)->pluck('text')->all();
    }
}
