<?php

namespace Tests\Feature\Telegram;

use App\Enums\MessageDirection;
use App\Models\BotMessage;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SergiX44\Nutgram\Nutgram;
use Tests\Concerns\InteractsWithBot;
use Tests\TestCase;

class ConversationLoggingTest extends TestCase
{
    use InteractsWithBot, RefreshDatabase;

    private Nutgram $bot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bot = $this->fakeBot();
    }

    public function test_answers_inside_order_conversation_are_logged(): void
    {
        $service = Service::factory()->create();

        $this->bot->hearText('/start')->reply();
        $this->bot->hearCallbackQueryData("order:create:{$service->id}")->reply();
        $this->bot->hearText('Николай')->reply();
        $this->bot->hearText('+79220032410')->reply();
        $this->bot->hearText('-')->reply();
        $this->bot->hearCallbackQueryData('order:confirm')->reply();

        $logged = BotMessage::query()
            ->where('direction', MessageDirection::In)
            ->pluck('text')
            ->all();

        $this->assertSame(['/start', 'Николай', '+79220032410', '-'], $logged);
    }
}
