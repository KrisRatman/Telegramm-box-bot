<?php

namespace Tests\Feature\Admin;

use App\Enums\MessageDirection;
use App\Enums\OrderStatus;
use App\Models\BotMessage;
use App\Models\Order;
use App\Services\OrderService;
use GuzzleHttp\Psr7\Request;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SergiX44\Nutgram\Nutgram;
use Tests\Concerns\InteractsWithBot;
use Tests\TestCase;

class OrderStatusTest extends TestCase
{
    use InteractsWithBot, RefreshDatabase;

    private Nutgram $bot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bot = $this->fakeBot();
    }

    public function test_status_change_notifies_client_in_bot(): void
    {
        $order = Order::factory()->create();

        app(OrderService::class)->changeStatus($order, OrderStatus::Confirmed);

        $this->bot->assertReply('sendMessage');
        $this->bot->assertRaw(function (Request $request) use ($order) {
            $text = $this->replyText($request);

            $this->assertStringContainsString($order->number, $text);
            $this->assertStringContainsString('Подтверждена', $text);

            return true;
        });
    }

    public function test_status_change_is_written_to_message_history(): void
    {
        $order = Order::factory()->create();

        app(OrderService::class)->changeStatus($order, OrderStatus::InProgress);

        $this->assertDatabaseHas('bot_messages', [
            'telegram_user_id' => $order->telegram_user_id,
            'direction' => MessageDirection::Out->value,
        ]);
    }

    public function test_same_status_does_not_notify(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::New]);

        app(OrderService::class)->changeStatus($order, OrderStatus::New);

        $this->bot->assertNoReply();
        $this->assertSame(0, BotMessage::query()->count());
    }

    public function test_completed_status_sets_completion_date(): void
    {
        $order = Order::factory()->create();

        app(OrderService::class)->changeStatus($order, OrderStatus::Completed);

        $this->assertNotNull($order->fresh()->completed_at);
    }

    public function test_direct_model_update_also_notifies_client(): void
    {
        $order = Order::factory()->create();

        // Правка через форму редактирования в админке — уведомление всё равно уходит.
        $order->update(['status' => OrderStatus::Cancelled]);

        $this->bot->assertReply('sendMessage');
    }
}
