<?php

namespace Tests\Feature\Telegram;

use App\Models\TelegramUser;
use App\Services\Telegram\BotMessenger;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SergiX44\Nutgram\Nutgram;
use Tests\TestCase;

/**
 * Telegram отвечает 403, когда пользователь заблокировал бота. Такие адреса
 * нужно помечать, иначе каждая рассылка будет биться об одни и те же чаты.
 */
class BlockedUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_is_marked_as_blocked_after_403(): void
    {
        $user = TelegramUser::factory()->create(['is_blocked' => false]);

        $messenger = new BotMessenger($this->botAnswering(403, 'Forbidden: bot was blocked by the user'));

        $this->assertFalse($messenger->sendToUser($user, 'Привет'));
        $this->assertTrue($user->fresh()->is_blocked);
        $this->assertSame(0, $user->messages()->count());
    }

    public function test_deactivated_account_is_also_marked(): void
    {
        $user = TelegramUser::factory()->create(['is_blocked' => false]);

        $messenger = new BotMessenger($this->botAnswering(403, 'Forbidden: user is deactivated'));

        $messenger->sendToUser($user, 'Привет');

        $this->assertTrue($user->fresh()->is_blocked);
    }

    public function test_other_errors_do_not_mark_user_as_blocked(): void
    {
        $user = TelegramUser::factory()->create(['is_blocked' => false]);

        $messenger = new BotMessenger($this->botAnswering(400, 'Bad Request: message text is empty'));

        $this->assertFalse($messenger->sendToUser($user, ''));
        $this->assertFalse($user->fresh()->is_blocked);
    }

    public function test_successful_delivery_clears_blocked_flag(): void
    {
        $user = TelegramUser::factory()->blocked()->create();

        $bot = Nutgram::fake();

        $this->assertTrue((new BotMessenger($bot))->sendToUser($user, 'Снова на связи'));
        $this->assertFalse($user->fresh()->is_blocked);
        $this->assertSame(1, $user->messages()->count());
    }

    private function botAnswering(int $errorCode, string $description): Nutgram
    {
        return Nutgram::fake(responses: [
            new Response(400, [], json_encode([
                'ok' => false,
                'error_code' => $errorCode,
                'description' => $description,
            ])),
        ]);
    }
}
