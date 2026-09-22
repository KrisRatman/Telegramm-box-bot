<?php

namespace Tests\Feature\Telegram;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['telegram.webhook_secret' => 'super-secret-token']);
    }

    public function test_request_without_secret_is_rejected(): void
    {
        $this->postJson('/telegram/webhook', $this->update())
            ->assertForbidden();
    }

    public function test_request_with_wrong_secret_is_rejected(): void
    {
        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'wrong')
            ->postJson('/telegram/webhook', $this->update())
            ->assertForbidden();
    }

    public function test_request_with_valid_secret_is_processed(): void
    {
        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'super-secret-token')
            ->postJson('/telegram/webhook', $this->update())
            ->assertOk();

        $this->assertDatabaseHas('telegram_users', ['chat_id' => 555001]);
    }

    public function test_webhook_without_configured_secret_fails_loudly(): void
    {
        config(['telegram.webhook_secret' => '']);

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'anything')
            ->postJson('/telegram/webhook', $this->update())
            ->assertStatus(500);
    }

    public function test_webhook_route_rejects_get(): void
    {
        $this->get('/telegram/webhook')->assertStatus(405);
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
