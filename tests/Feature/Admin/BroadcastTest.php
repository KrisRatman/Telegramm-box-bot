<?php

namespace Tests\Feature\Admin;

use App\Enums\BroadcastStatus;
use App\Enums\MessageDirection;
use App\Jobs\SendBroadcastMessage;
use App\Jobs\StartBroadcast;
use App\Models\BotMessage;
use App\Models\Broadcast;
use App\Models\TelegramUser;
use App\Services\BroadcastService;
use App\Services\Telegram\BotMessenger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use SergiX44\Nutgram\Nutgram;
use Tests\Concerns\InteractsWithBot;
use Tests\TestCase;

class BroadcastTest extends TestCase
{
    use InteractsWithBot, RefreshDatabase;

    private Nutgram $bot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bot = $this->fakeBot();
    }

    public function test_queueing_a_broadcast_does_not_send_immediately(): void
    {
        Queue::fake();

        TelegramUser::factory()->count(3)->create();
        $broadcast = Broadcast::factory()->create();

        app(BroadcastService::class)->queue($broadcast);

        Queue::assertPushed(StartBroadcast::class);

        $broadcast->refresh();
        $this->assertSame(BroadcastStatus::Queued, $broadcast->status);
        $this->assertSame(3, $broadcast->recipients_count);
        $this->bot->assertNoReply();
    }

    public function test_blocked_users_are_excluded_from_recipients(): void
    {
        Queue::fake();

        TelegramUser::factory()->count(2)->create();
        TelegramUser::factory()->blocked()->count(5)->create();

        $broadcast = Broadcast::factory()->create();
        app(BroadcastService::class)->queue($broadcast);

        $this->assertSame(2, $broadcast->refresh()->recipients_count);
    }

    public function test_start_job_creates_one_job_per_recipient(): void
    {
        Bus::fake();

        TelegramUser::factory()->count(4)->create();
        $broadcast = Broadcast::factory()->create(['status' => BroadcastStatus::Queued]);

        (new StartBroadcast($broadcast->id))->handle();

        Bus::assertBatched(fn ($batch) => $batch->jobs->count() === 4);
        $this->assertSame(BroadcastStatus::Sending, $broadcast->refresh()->status);
    }

    public function test_broadcast_without_recipients_finishes_immediately(): void
    {
        Bus::fake();

        $broadcast = Broadcast::factory()->create(['status' => BroadcastStatus::Queued]);

        (new StartBroadcast($broadcast->id))->handle();

        $broadcast->refresh();
        $this->assertSame(BroadcastStatus::Sent, $broadcast->status);
        $this->assertNotNull($broadcast->finished_at);
    }

    public function test_message_job_delivers_and_counts(): void
    {
        config(['telegram.broadcast.delay_ms' => 0]);

        $user = TelegramUser::factory()->create();
        $broadcast = Broadcast::factory()->create([
            'status' => BroadcastStatus::Sending,
            'message' => 'Текст рассылки',
        ]);

        (new SendBroadcastMessage($broadcast->id, $user->id))
            ->handle(app(BotMessenger::class));

        $this->bot->assertReply('sendMessage');
        $this->assertSame(1, $broadcast->refresh()->sent_count);

        $this->assertDatabaseHas('bot_messages', [
            'telegram_user_id' => $user->id,
            'broadcast_id' => $broadcast->id,
            'direction' => MessageDirection::Out->value,
            'text' => 'Текст рассылки',
        ]);
    }

    public function test_message_job_ignores_missing_records(): void
    {
        config(['telegram.broadcast.delay_ms' => 0]);

        (new SendBroadcastMessage(999, 999))
            ->handle(app(BotMessenger::class));

        $this->bot->assertNoReply();
        $this->assertSame(0, BotMessage::query()->count());
    }

    public function test_requeue_resets_counters(): void
    {
        Queue::fake();

        $broadcast = Broadcast::factory()->create([
            'status' => BroadcastStatus::Failed,
            'sent_count' => 10,
            'failed_count' => 4,
        ]);

        app(BroadcastService::class)->queue($broadcast);

        $broadcast->refresh();
        $this->assertSame(0, $broadcast->sent_count);
        $this->assertSame(0, $broadcast->failed_count);
    }
}
