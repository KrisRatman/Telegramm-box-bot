<?php

namespace Tests\Feature\Telegram;

use App\Enums\MessageDirection;
use App\Enums\OrderStatus;
use App\Models\BotMessage;
use App\Models\Order;
use App\Models\Service;
use GuzzleHttp\Psr7\Request;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SergiX44\Nutgram\Nutgram;
use Tests\Concerns\InteractsWithBot;
use Tests\TestCase;

class OrderConversationTest extends TestCase
{
    use InteractsWithBot, RefreshDatabase;

    private Nutgram $bot;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bot = $this->fakeBot();
        $this->service = Service::factory()->create([
            'name' => 'Бот для заявок',
            'price' => 25000,
        ]);
    }

    public function test_full_order_flow_creates_order(): void
    {
        $this->startOrder();

        $this->bot->hearText('Иван')->reply();
        $this->bot->hearText('+7 900 123-45-67')->reply();
        $this->bot->hearText('Позвоните после 18:00')->reply();

        $this->bot->assertRaw(
            fn (Request $request) => str_contains($this->replyText($request), 'Проверьте заявку')
        );

        $this->bot->hearCallbackQueryData('order:confirm')->reply();

        $order = Order::query()->firstOrFail();

        $this->assertSame('Бот для заявок', $order->service_name);
        $this->assertSame('25000.00', $order->price);
        $this->assertSame(OrderStatus::New, $order->status);
        $this->assertSame('Иван', $order->contact_name);
        $this->assertSame('+7 900 123-45-67', $order->contact_phone);
        $this->assertSame('Позвоните после 18:00', $order->comment);
        $this->assertNotEmpty($order->number);
    }

    public function test_order_stores_service_name_and_price_as_snapshot(): void
    {
        $this->completeOrder();

        $this->service->update(['name' => 'Другое название', 'price' => 99000]);

        $order = Order::query()->firstOrFail();

        $this->assertSame('Бот для заявок', $order->service_name);
        $this->assertSame('25000.00', $order->price);
    }

    public function test_dash_comment_is_stored_as_empty(): void
    {
        $this->startOrder();

        $this->bot->hearText('Иван')->reply();
        $this->bot->hearText('+79001234567')->reply();
        $this->bot->hearText('-')->reply();
        $this->bot->hearCallbackQueryData('order:confirm')->reply();

        $this->assertNull(Order::query()->firstOrFail()->comment);
    }

    public function test_invalid_phone_is_rejected(): void
    {
        $this->startOrder();

        $this->bot->hearText('Иван')->reply();
        $this->bot->hearText('телефона нет')->reply();

        $this->bot->assertRaw(
            fn (Request $request) => str_contains($this->replyText($request), 'Не похоже на номер телефона')
        );

        // Шаг не сменился: бот по-прежнему ждёт телефон.
        $this->bot->hearText('+79001234567')->reply();

        $this->bot->assertRaw(
            fn (Request $request) => str_contains($this->replyText($request), 'комментарий')
        );
    }

    public function test_cancel_button_aborts_order(): void
    {
        $this->startOrder();

        $this->bot->hearText('Иван')->reply();
        $this->bot->hearCallbackQueryData('order:cancel')->reply();

        $this->assertSame(0, Order::query()->count());
        $this->bot->assertNoConversation(424242, 424242);
    }

    public function test_order_appears_in_my_orders(): void
    {
        $this->completeOrder();

        $this->bot->hearCallbackQueryData('orders:my')->reply();

        $this->bot->assertRaw(function (Request $request) {
            $this->assertStringContainsString('Бот для заявок', $this->replyText($request));
            $this->assertStringContainsString('Новая', $this->replyText($request));

            return true;
        });
    }

    public function test_outgoing_messages_are_logged(): void
    {
        $this->completeOrder();

        $this->assertTrue(
            BotMessage::query()->where('direction', MessageDirection::In)->exists(),
            'Входящие сообщения не записаны.',
        );
    }

    private function startOrder(): void
    {
        $this->bot->hearText('/start')->reply();
        $this->bot->hearCallbackQueryData("order:create:{$this->service->id}")->reply();
    }

    private function completeOrder(): void
    {
        $this->startOrder();

        $this->bot->hearText('Иван')->reply();
        $this->bot->hearText('+79001234567')->reply();
        $this->bot->hearText('-')->reply();
        $this->bot->hearCallbackQueryData('order:confirm')->reply();
    }
}
