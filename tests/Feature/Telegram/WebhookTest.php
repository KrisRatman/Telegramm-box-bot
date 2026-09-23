<?php

namespace Tests\Feature\Telegram;

use App\Models\Bot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    private Bot $bot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bot = Bot::factory()->create();
    }

    public function test_request_without_secret_is_rejected(): void
    {
        $this->postJson($this->url(), $this->update())
            ->assertForbidden();
    }

    public function test_request_with_wrong_secret_is_rejected(): void
    {
        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'wrong')
            ->postJson($this->url(), $this->update())
            ->assertForbidden();
    }

    public function test_request_with_valid_secret_is_processed_for_that_bot(): void
    {
        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', $this->bot->webhook_secret)
            ->postJson($this->url(), $this->update())
            ->assertOk();

        $this->assertDatabaseHas('telegram_users', ['chat_id' => 555001, 'bot_id' => $this->bot->id]);
    }

    public function test_secret_of_another_bot_is_rejected(): void
    {
        $other = Bot::factory()->create();

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', $other->webhook_secret)
            ->postJson($this->url(), $this->update())
            ->assertForbidden();
    }

    public function test_inactive_bot_does_not_accept_updates(): void
    {
        $this->bot->update(['is_active' => false]);

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', $this->bot->webhook_secret)
            ->postJson($this->url(), $this->update())
            ->assertNotFound();
    }

    public function test_same_person_in_two_bots_is_two_users(): void
    {
        $other = Bot::factory()->create();

        foreach ([$this->bot, $other] as $bot) {
            $this->withHeader('X-Telegram-Bot-Api-Secret-Token', $bot->webhook_secret)
                ->postJson("/telegram/webhook/{$bot->id}", $this->update())
                ->assertOk();
        }

        $this->assertDatabaseCount('telegram_users', 2);
    }

    public function test_webhook_route_rejects_get(): void
    {
        $this->get($this->url())->assertStatus(405);
    }

    private function url(): string
    {
        return "/telegram/webhook/{$this->bot->id}";
    }

    /**
     * @return array<string, mixed>
     */
    private function update(): array
    {
        return [
            'update_id' => 1,
            'message' => [
                'message_id' => 1,
                'date' => 1703892479,
                'text' => '/start',
                'from' => [
                    'id' => 555001,
                    'is_bot' => false,
                    'first_name' => 'Мария',
                    'username' => 'maria',
                    'language_code' => 'ru',
                ],
                'chat' => [
                    'id' => 555001,
                    'type' => 'private',
                    'first_name' => 'Мария',
                ],
            ],
        ];
    }
}
