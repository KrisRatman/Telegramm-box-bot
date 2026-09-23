<?php

namespace Tests\Feature\Telegram;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Service;
use App\Models\TelegramUser;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SergiX44\Nutgram\Nutgram;
use Tests\Concerns\InteractsWithBot;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use InteractsWithBot, RefreshDatabase;

    private const CHAT_ID = 424242;

    private const ADMIN_CHAT_ID = 111;

    public function test_english_telegram_user_gets_english_menu(): void
    {
        $bot = $this->englishBot();

        $bot->hearText('/start')->reply();

        $reply = $this->requestsTo($bot, 'sendMessage')[0];
        $this->assertStringContainsString('Hello', $reply['text']);
        $this->assertSame('🛍 Service catalog', $reply['reply_markup']['inline_keyboard'][0][0]['text']);
    }

    public function test_unsupported_telegram_language_falls_back_to_bot_default(): void
    {
        $botModel = $this->testBot();
        $botModel->update(['default_locale' => 'en']);
        $bot = $this->fakeBot(self::CHAT_ID, bot: $botModel, languageCode: 'de');

        $bot->hearText('/start')->reply();

        $this->assertStringContainsString('Hello', $this->requestsTo($bot, 'sendMessage')[0]['text']);
    }

    public function test_language_choice_overrides_telegram_language(): void
    {
        $bot = $this->fakeBot(self::CHAT_ID);

        $bot->hearCallbackQueryData('lang:set:en')->reply();

        $this->assertSame('en', TelegramUser::query()->sole()->locale);
        $this->assertStringContainsString('I speak English', $this->requestsTo($bot, 'editMessageText')[0]['text']);

        $bot->hearText('/start')->reply();

        $this->assertStringContainsString('Hello', $this->requestsTo($bot, 'sendMessage')[0]['text']);
    }

    public function test_unknown_language_code_is_ignored(): void
    {
        $bot = $this->fakeBot(self::CHAT_ID);

        $bot->hearCallbackQueryData('lang:set:xx')->reply();

        $this->assertNull(TelegramUser::query()->sole()->locale);
    }

    public function test_catalog_uses_translation_and_falls_back_to_russian(): void
    {
        $bot = $this->englishBot();
        $translated = Service::factory()->create([
            'name' => 'Лендинг',
            'translations' => ['en' => ['name' => 'Landing page', 'description' => 'One page']],
        ]);
        $untranslated = Service::factory()->create(['name' => 'Консультация']);

        $bot->hearCallbackQueryData("catalog:service:{$translated->id}")->reply();
        $this->assertStringContainsString('Landing page', $this->requestsTo($bot, 'editMessageText')[0]['text']);

        $bot->hearCallbackQueryData("catalog:service:{$untranslated->id}")->reply();
        $this->assertStringContainsString('Консультация', $this->requestsTo($bot, 'editMessageText')[0]['text']);
    }

    public function test_status_change_from_admin_reaches_client_in_client_language(): void
    {
        $nutgram = $this->fakeBot();
        $client = TelegramUser::factory()->create(['language_code' => 'en']);
        $order = Order::factory()->for($client)->create();

        // Админка работает на русском — клиенту всё равно уходит английский.
        app()->setLocale('ru');
        app(OrderService::class)->changeStatus($order, OrderStatus::Confirmed);

        $message = $this->requestsTo($nutgram, 'sendMessage')[0]['text'];
        $this->assertStringContainsString('status: <b>Confirmed</b>', $message);
        $this->assertSame('ru', app()->getLocale());
    }

    public function test_admin_notification_stays_russian_for_english_client(): void
    {
        config(['telegram.admin_chat_ids' => [(string) self::ADMIN_CHAT_ID]]);
        $bot = $this->englishBot();
        $service = Service::factory()->create([
            'name' => 'Лендинг',
            'translations' => ['en' => ['name' => 'Landing page']],
        ]);

        $bot->hearCallbackQueryData("order:create:{$service->id}")->reply();
        $bot->hearText('John')->reply();
        $bot->hearText('+79001234567')->reply();
        $bot->hearText('-')->reply();
        $bot->hearCallbackQueryData('order:confirm')->reply();

        $messages = collect($this->requestsTo($bot, 'sendMessage'));
        $toAdmin = $messages->firstWhere('chat_id', self::ADMIN_CHAT_ID)['text'];
        $toClient = $this->requestsTo($bot, 'editMessageText')[0]['text'];

        $this->assertStringContainsString('Новая заявка', $toAdmin);
        $this->assertStringContainsString('Услуга: Лендинг', $toAdmin);
        $this->assertStringContainsString('Request <b>#', $toClient);
        $this->assertStringContainsString('Service: Landing page', $toClient);
    }

    public function test_mini_app_page_ships_both_languages(): void
    {
        $this->withoutVite();
        $bot = $this->testBot();
        Service::factory()->create(['name' => 'Лендинг', 'translations' => ['en' => ['name' => 'Landing page']]]);

        $this->get("/app/{$bot->id}")
            ->assertOk()
            ->assertSee('Landing page')
            ->assertSee('Add to cart')
            ->assertSee('В корзину');
    }

    private function englishBot(): Nutgram
    {
        return $this->fakeBot(self::CHAT_ID, 'John', languageCode: 'en');
    }
}
