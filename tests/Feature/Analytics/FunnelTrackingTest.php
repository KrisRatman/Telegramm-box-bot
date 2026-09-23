<?php

namespace Tests\Feature\Analytics;

use App\Enums\BotEventType;
use App\Enums\OrderSource;
use App\Models\Bot;
use App\Models\BotEvent;
use App\Models\Order;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithBot;
use Tests\TestCase;

class FunnelTrackingTest extends TestCase
{
    use InteractsWithBot, RefreshDatabase;

    private const BOT_TOKEN = '123456:test-bot-token';

    private const CHAT_ID = 424242;

    private Bot $botModel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->botModel = Bot::factory()->create(['token' => self::BOT_TOKEN]);
    }

    public function test_bot_records_catalog_view_and_order_start(): void
    {
        $service = Service::factory()->create();
        $bot = $this->fakeBot(self::CHAT_ID);

        $bot->hearCallbackQueryData('catalog:list')->reply();
        $bot->hearCallbackQueryData("order:create:{$service->id}")->reply();

        $this->assertSame(
            [['catalog_viewed', 'bot'], ['order_started', 'bot']],
            BotEvent::query()->orderBy('id')->get()
                ->map(fn (BotEvent $event) => [$event->type->value, $event->source->value])
                ->all(),
        );
    }

    public function test_bot_order_is_marked_as_bot_source(): void
    {
        $service = Service::factory()->create();
        $bot = $this->fakeBot(self::CHAT_ID);

        $bot->hearCallbackQueryData("order:create:{$service->id}")->reply();
        $bot->hearText('Иван')->reply();
        $bot->hearText('+79001234567')->reply();
        $bot->hearText('-')->reply();
        $bot->hearCallbackQueryData('order:confirm')->reply();

        $this->assertSame(OrderSource::Bot, Order::query()->sole()->refresh()->source);
    }

    public function test_mini_app_records_event_and_marks_cart_order(): void
    {
        $service = Service::factory()->create();

        $this->postJson("/app/{$this->botModel->id}/api/events", ['type' => 'order_started'], $this->miniAppHeaders())
            ->assertNoContent();

        $this->postJson("/app/{$this->botModel->id}/api/orders", [
            'items' => [['service_id' => $service->id, 'quantity' => 1]],
            'contact_name' => 'Иван',
            'contact_phone' => '+79001234567',
        ], $this->miniAppHeaders())->assertCreated();

        $event = BotEvent::query()->sole();
        $this->assertSame(BotEventType::OrderStarted, $event->type);
        $this->assertSame(OrderSource::MiniApp, $event->source);
        $this->assertSame(OrderSource::MiniApp, Order::query()->sole()->refresh()->source);
    }

    public function test_mini_app_rejects_unknown_event_type(): void
    {
        $this->postJson("/app/{$this->botModel->id}/api/events", ['type' => 'order_paid'], $this->miniAppHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');

        $this->assertDatabaseCount('bot_events', 0);
    }

    public function test_mini_app_event_requires_init_data(): void
    {
        $this->postJson("/app/{$this->botModel->id}/api/events", ['type' => 'catalog_viewed'])->assertUnauthorized();
    }

    /**
     * @return array<string, string>
     */
    private function miniAppHeaders(): array
    {
        $fields = [
            'auth_date' => (string) now()->timestamp,
            'user' => json_encode(['id' => self::CHAT_ID, 'first_name' => 'Иван'], JSON_UNESCAPED_UNICODE),
        ];

        ksort($fields);
        $checkString = collect($fields)->map(fn (string $value, string $key) => "{$key}={$value}")->implode("\n");
        $fields['hash'] = hash_hmac('sha256', $checkString, hash_hmac('sha256', self::BOT_TOKEN, 'WebAppData', true));

        return ['X-Telegram-Init-Data' => http_build_query($fields)];
    }
}
