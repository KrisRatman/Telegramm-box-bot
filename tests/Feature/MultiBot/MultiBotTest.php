<?php

namespace Tests\Feature\MultiBot;

use App\Enums\OrderStatus;
use App\Jobs\StartBroadcast;
use App\Models\Bot;
use App\Models\Broadcast;
use App\Models\Order;
use App\Models\Service;
use App\Models\TelegramUser;
use App\Services\Analytics\AnalyticsService;
use App\Services\Analytics\Period;
use App\Services\BroadcastService;
use App\Services\Telegram\BotMessenger;
use App\Telegram\BotManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use SergiX44\Nutgram\Nutgram;
use Tests\Concerns\InteractsWithBot;
use Tests\TestCase;

class MultiBotTest extends TestCase
{
    use InteractsWithBot, RefreshDatabase;

    public function test_tokens_are_encrypted_and_hidden(): void
    {
        $bot = Bot::factory()->create(['token' => '123:secret-token', 'payment_provider_token' => 'pay-secret']);

        $raw = DB::table('bots')->where('id', $bot->id)->first();

        $this->assertNotSame('123:secret-token', $raw->token);
        $this->assertStringNotContainsString('pay-secret', (string) $raw->payment_provider_token);
        $this->assertSame('123:secret-token', $bot->fresh()->token);
        $this->assertArrayNotHasKey('token', $bot->toArray());
        $this->assertArrayNotHasKey('webhook_secret', $bot->toArray());
    }

    public function test_message_goes_out_through_the_users_own_bot(): void
    {
        [$first, $second] = Bot::factory()->count(2)->create();
        $fakes = [$first->id => Nutgram::fake(), $second->id => Nutgram::fake()];
        $messenger = new BotMessenger(new BotManager(app(), fn (Bot $bot) => $fakes[$bot->id]));

        $client = TelegramUser::factory()->for($second)->create();

        $messenger->sendToUser($client, 'Ответ администратора');

        $this->assertSame([], $this->requestsTo($fakes[$first->id], 'sendMessage'));
        $this->assertSame('Ответ администратора', $this->requestsTo($fakes[$second->id], 'sendMessage')[0]['text']);
    }

    public function test_foreign_nutgram_instance_is_matched_to_bot_by_token(): void
    {
        // Так работает штатный nutgram:run: экземпляр из контейнера с токеном из .env.
        $nutgram = Nutgram::fake();
        Bot::factory()->create();
        $bot = Bot::factory()->create(['token' => $nutgram->getBotId().':token-from-env']);

        $this->assertTrue((new BotManager(app()))->botOf($nutgram)->is($bot));
    }

    public function test_bot_catalog_shows_only_services_sold_in_that_bot(): void
    {
        [$main, $branch] = Bot::factory()->count(2)->create();
        $everywhere = Service::factory()->create(['name' => 'Лендинг']);
        $mainOnly = Service::factory()->create(['name' => 'Только в основном']);
        $mainOnly->bots()->detach($branch);

        $bot = $this->fakeBot(bot: $branch);

        $bot->hearCallbackQueryData("catalog:category:{$everywhere->service_category_id}")->reply();
        $screen = $this->requestsTo($bot, 'editMessageText')[0];
        $buttons = collect($screen['reply_markup']['inline_keyboard'])->flatten(1)->pluck('text')->implode("\n");
        $this->assertStringContainsString('Лендинг', $buttons);

        $bot->hearCallbackQueryData("catalog:category:{$mainOnly->service_category_id}")->reply();
        $screen = $this->requestsTo($bot, 'editMessageText')[0];
        $this->assertStringContainsString('В этой категории пока нет услуг', $screen['text']);
        $this->assertStringNotContainsString('Только в основном', json_encode($screen, JSON_UNESCAPED_UNICODE));
    }

    public function test_mini_app_rejects_service_not_sold_in_that_bot(): void
    {
        [$main, $branch] = Bot::factory()->count(2)->create(['token' => '123456:shared-test-token']);
        $service = Service::factory()->create();
        $service->bots()->detach($branch);

        $this->postJson("/app/{$branch->id}/api/orders", [
            'items' => [['service_id' => $service->id, 'quantity' => 1]],
            'contact_name' => 'Иван',
            'contact_phone' => '+79001234567',
        ], ['X-Telegram-Init-Data' => $this->initData('123456:shared-test-token')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_init_data_of_one_bot_is_not_accepted_by_another(): void
    {
        $first = Bot::factory()->create(['token' => '111:first-bot']);
        $second = Bot::factory()->create(['token' => '222:second-bot']);

        $this->getJson("/app/{$first->id}/api/profile", ['X-Telegram-Init-Data' => $this->initData('111:first-bot')])->assertOk();
        $this->getJson("/app/{$second->id}/api/profile", ['X-Telegram-Init-Data' => $this->initData('111:first-bot')])->assertUnauthorized();
    }

    public function test_order_and_payments_belong_to_the_client_bot(): void
    {
        $withPayments = Bot::factory()->withPayments()->create();
        $withoutPayments = Bot::factory()->create();

        $paidBotOrder = Order::factory()->for(TelegramUser::factory()->for($withPayments))->create();
        $freeBotOrder = Order::factory()->for(TelegramUser::factory()->for($withoutPayments))->create();

        $this->assertSame($withPayments->id, $paidBotOrder->bot_id);
        $this->assertTrue($paidBotOrder->bot->paymentsEnabled());
        $this->assertFalse($freeBotOrder->bot->paymentsEnabled());
    }

    public function test_broadcast_reaches_only_subscribers_of_its_bot(): void
    {
        Queue::fake();
        [$main, $branch] = Bot::factory()->count(2)->create();
        TelegramUser::factory()->count(3)->for($main)->create();
        TelegramUser::factory()->count(2)->for($branch)->create();
        TelegramUser::factory()->blocked()->for($branch)->create();

        $broadcast = app(BroadcastService::class)->queue(Broadcast::factory()->for($branch)->create());

        $this->assertSame(2, $broadcast->recipients_count);
        Queue::assertPushed(StartBroadcast::class);
    }

    public function test_analytics_can_be_filtered_by_bot(): void
    {
        [$main, $branch] = Bot::factory()->count(2)->create();
        Order::factory()->count(2)->for(TelegramUser::factory()->for($main))->create();
        Order::factory()->for(TelegramUser::factory()->for($branch))->status(OrderStatus::Confirmed)->create();

        $analytics = app(AnalyticsService::class);

        $this->assertSame(3, $analytics->summary(Period::lastDays(7))['orders']);
        $this->assertSame(2, $analytics->summary(Period::lastDays(7, $main->id))['orders']);
        $this->assertSame(1, $analytics->summary(Period::lastDays(7, $branch->id))['new_users']);
    }

    public function test_new_bot_sells_whole_active_catalog(): void
    {
        $active = Service::factory()->create();
        $inactive = Service::factory()->inactive()->create();

        $bot = Bot::factory()->create();

        $this->assertTrue($bot->services()->whereKey($active)->exists());
        $this->assertFalse($bot->services()->whereKey($inactive)->exists());
    }

    public function test_webhook_command_sets_url_and_secret_of_each_active_bot(): void
    {
        config(['app.url' => 'https://example.test']);
        $bot = Bot::factory()->create();
        Bot::factory()->create(['is_active' => false]);
        $nutgram = $this->fakeBot(bot: $bot);

        $this->artisan('telegram:webhook', ['action' => 'set'])->assertSuccessful();

        $calls = $this->requestsTo($nutgram, 'setWebhook');
        $this->assertCount(1, $calls);
        $this->assertSame("https://example.test/telegram/webhook/{$bot->id}", $calls[0]['url']);
        $this->assertSame($bot->webhook_secret, $calls[0]['secret_token']);
    }

    public function test_commands_are_registered_for_every_language(): void
    {
        $nutgram = $this->fakeBot(bot: Bot::factory()->create());

        $this->artisan('telegram:commands')->assertSuccessful();

        $calls = collect($this->requestsTo($nutgram, 'setMyCommands'));
        $english = $calls->firstWhere('language_code', 'en');
        $commands = is_string($english['commands']) ? json_decode($english['commands'], true) : $english['commands'];

        $this->assertSame(['ru', 'en', null], $calls->pluck('language_code')->all());
        $this->assertContains(['command' => 'language', 'description' => 'Change language'], $commands);
    }

    private function initData(string $token): string
    {
        $fields = [
            'auth_date' => (string) now()->timestamp,
            'user' => json_encode(['id' => 424242, 'first_name' => 'Иван'], JSON_UNESCAPED_UNICODE),
        ];

        ksort($fields);
        $checkString = collect($fields)->map(fn (string $value, string $key) => "{$key}={$value}")->implode("\n");
        $fields['hash'] = hash_hmac('sha256', $checkString, hash_hmac('sha256', $token, 'WebAppData', true));

        return http_build_query($fields);
    }
}
