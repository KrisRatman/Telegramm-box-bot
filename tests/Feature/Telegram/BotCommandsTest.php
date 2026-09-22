<?php

namespace Tests\Feature\Telegram;

use App\Enums\MessageDirection;
use App\Models\BotMessage;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\TelegramUser;
use GuzzleHttp\Psr7\Request;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SergiX44\Nutgram\Nutgram;
use Tests\Concerns\InteractsWithBot;
use Tests\TestCase;

class BotCommandsTest extends TestCase
{
    use InteractsWithBot, RefreshDatabase;

    private Nutgram $bot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bot = $this->fakeBot();
    }

    public function test_start_command_registers_user_and_shows_menu(): void
    {
        $this->bot->hearText('/start')->reply();

        $this->bot->assertReply('sendMessage');

        $this->bot->assertRaw(function (Request $request) {
            $this->assertStringContainsString('Здравствуйте', $this->replyText($request));
            $this->assertContains('🛍 Каталог услуг', $this->replyButtons($request));

            return true;
        });

        $this->assertDatabaseHas('telegram_users', [
            'chat_id' => 424242,
            'username' => 'ivan',
            'first_name' => 'Иван',
        ]);
    }

    public function test_start_command_does_not_duplicate_user(): void
    {
        $this->bot->hearText('/start')->reply();
        $this->bot->hearText('/start')->reply();

        $this->assertSame(1, TelegramUser::query()->where('chat_id', 424242)->count());
    }

    public function test_incoming_messages_are_saved_to_history(): void
    {
        $this->bot->hearText('/start')->reply();

        $this->assertDatabaseHas('bot_messages', [
            'direction' => 'in',
            'text' => '/start',
        ]);
    }

    public function test_button_presses_are_not_logged_as_user_messages(): void
    {
        Service::factory()->create(['name' => 'Лендинг']);

        $this->bot->hearText('/start')->reply();
        $this->bot->hearCallbackQueryData('catalog:list')->reply();
        $this->bot->hearCallbackQueryData('menu:help')->reply();

        // В историю попадает только реально набранный пользователем текст.
        $this->assertSame(['/start'], BotMessage::query()
            ->where('direction', MessageDirection::In)
            ->pluck('text')
            ->all());
    }

    public function test_bot_replies_are_never_stored_as_incoming(): void
    {
        $this->bot->hearText('/start')->reply();
        $this->bot->hearCallbackQueryData('menu:main')->reply();

        $this->assertFalse(
            BotMessage::query()
                ->where('direction', MessageDirection::In)
                ->where('text', 'like', '%Здравствуйте%')
                ->exists(),
            'Ответ бота записан как входящее сообщение клиента.',
        );
    }

    public function test_catalog_shows_only_active_categories(): void
    {
        $visible = ServiceCategory::factory()->create(['name' => 'Видимая категория']);
        Service::factory()->for($visible, 'category')->create();

        $hidden = ServiceCategory::factory()->inactive()->create(['name' => 'Скрытая категория']);
        Service::factory()->for($hidden, 'category')->create();

        $this->bot->hearCallbackQueryData('catalog:list')->reply();

        $this->bot->assertRaw(function (Request $request) {
            $buttons = $this->replyButtons($request);

            $this->assertContains('Видимая категория', $buttons);
            $this->assertNotContains('Скрытая категория', $buttons);

            return true;
        });
    }

    public function test_empty_catalog_shows_placeholder(): void
    {
        $this->bot->hearCallbackQueryData('catalog:list')->reply();

        $this->bot->assertRaw(
            fn (Request $request) => str_contains($this->replyText($request), 'Каталог пока пуст')
        );
    }

    public function test_service_card_shows_price_and_order_button(): void
    {
        $service = Service::factory()->create([
            'name' => 'Лендинг',
            'price' => 35000,
        ]);

        $this->bot->hearCallbackQueryData("catalog:service:{$service->id}")->reply();

        $this->bot->assertRaw(function (Request $request) {
            $this->assertStringContainsString('Лендинг', $this->replyText($request));
            $this->assertStringContainsString('35 000', $this->replyText($request));
            $this->assertContains('✅ Оставить заявку', $this->replyButtons($request));

            return true;
        });
    }

    public function test_inactive_service_card_falls_back_to_catalog(): void
    {
        $service = Service::factory()->inactive()->create(['name' => 'Снятая услуга']);

        $this->bot->hearCallbackQueryData("catalog:service:{$service->id}")->reply();

        $this->bot->assertRaw(
            fn (Request $request) => ! str_contains($this->replyText($request), 'Снятая услуга')
        );
    }

    public function test_orders_command_reports_empty_list(): void
    {
        $this->bot->hearText('/orders')->reply();

        $this->bot->assertRaw(
            fn (Request $request) => str_contains($this->replyText($request), 'пока нет заявок')
        );
    }

    public function test_help_command_answers(): void
    {
        $this->bot->hearText('/help')->reply();

        $this->bot->assertRaw(
            fn (Request $request) => str_contains($this->replyText($request), 'Как это работает')
        );
    }
}
