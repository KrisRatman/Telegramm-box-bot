<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Orders\RelationManagers\PaymentsRelationManager;
use App\Models\Bot;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use SergiX44\Nutgram\Nutgram;
use Tests\Concerns\InteractsWithBot;
use Tests\TestCase;

class PaymentAdminTest extends TestCase
{
    use InteractsWithBot, RefreshDatabase;

    private Nutgram $bot;

    protected function setUp(): void
    {
        parent::setUp();

        Bot::factory()->withPayments()->create();

        $this->bot = $this->fakeBot();
        $this->actingAs(User::factory()->create());
    }

    public function test_admin_sends_invoice_from_order_card(): void
    {
        $order = Order::factory()->create(['price' => 4000]);

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('sendInvoice')
            ->assertNotified('Счёт отправлен');

        $payment = Payment::query()->sole();

        $this->assertSame($order->id, $payment->order_id);
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->bot->assertCalled('sendInvoice');
    }

    public function test_send_invoice_is_hidden_for_paid_and_cancelled_orders(): void
    {
        $paid = Order::factory()->create(['paid_at' => now()]);
        $cancelled = Order::factory()->status(OrderStatus::Cancelled)->create();

        Livewire::test(ViewOrder::class, ['record' => $paid->getRouteKey()])
            ->assertActionHidden('sendInvoice');

        Livewire::test(ViewOrder::class, ['record' => $cancelled->getRouteKey()])
            ->assertActionHidden('sendInvoice');
    }

    public function test_send_invoice_is_hidden_without_provider_token(): void
    {
        Bot::query()->update(['payment_provider_token' => null]);
        $order = Order::factory()->create();

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->assertActionHidden('sendInvoice');
    }

    public function test_marking_refund_updates_payment_and_order(): void
    {
        $order = Order::factory()->create(['paid_at' => now()]);
        $payment = Payment::factory()->forOrder($order)->paid()->create();

        Livewire::test(PaymentsRelationManager::class, [
            'ownerRecord' => $order,
            'pageClass' => ViewOrder::class,
        ])->callAction(TestAction::make('markRefunded')->table($payment));

        $payment->refresh();

        $this->assertSame(PaymentStatus::Refunded, $payment->status);
        $this->assertNotNull($payment->refunded_at);
        $this->assertFalse($order->fresh()->isPaid());

        $this->bot->assertCalled('sendMessage');
        $this->assertDatabaseHas('bot_messages', ['telegram_user_id' => $order->telegram_user_id]);
    }

    public function test_refund_of_one_payment_keeps_order_paid_by_another(): void
    {
        $order = Order::factory()->create(['paid_at' => now()]);
        $refunded = Payment::factory()->forOrder($order)->paid()->create();
        Payment::factory()->forOrder($order)->paid()->create();

        Livewire::test(PaymentsRelationManager::class, [
            'ownerRecord' => $order,
            'pageClass' => ViewOrder::class,
        ])->callAction(TestAction::make('markRefunded')->table($refunded));

        $this->assertTrue($order->fresh()->isPaid());
    }

    public function test_refund_action_is_hidden_for_pending_payment(): void
    {
        $order = Order::factory()->create();
        $payment = Payment::factory()->forOrder($order)->create();

        Livewire::test(PaymentsRelationManager::class, [
            'ownerRecord' => $order,
            'pageClass' => ViewOrder::class,
        ])->assertActionHidden(TestAction::make('markRefunded')->table($payment));
    }

    public function test_order_card_shows_payments(): void
    {
        $order = Order::factory()->create(['paid_at' => now()]);
        Payment::factory()->forOrder($order)->paid()->create(['provider_payment_charge_id' => 'yk-2f1a']);

        // Relation manager грузится лениво, поэтому страницу проверяем
        // на рендер, а содержимое таблицы — отдельно.
        $this->get("/admin/orders/{$order->id}")->assertOk();

        Livewire::test(PaymentsRelationManager::class, [
            'ownerRecord' => $order,
            'pageClass' => ViewOrder::class,
        ])->assertSee('yk-2f1a');
    }

    public function test_orders_list_filters_by_payment(): void
    {
        $paid = Order::factory()->create(['paid_at' => now()]);
        $unpaid = Order::factory()->create();

        Livewire::test(ListOrders::class)
            ->filterTable('paid', true)
            ->assertCanSeeTableRecords([$paid])
            ->assertCanNotSeeTableRecords([$unpaid]);
    }
}
