<?php

namespace App\Jobs;

use App\Models\Broadcast;
use App\Models\TelegramUser;
use App\Services\Telegram\BotMessenger;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Одно сообщение рассылки. Отдельная задача на получателя, чтобы сбой
 * доставки одному человеку не рушил всю рассылку.
 */
class SendBroadcastMessage implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $broadcastId,
        public readonly int $telegramUserId,
    ) {}

    public function handle(BotMessenger $messenger): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $broadcast = Broadcast::find($this->broadcastId);
        $user = TelegramUser::find($this->telegramUserId);

        if ($broadcast === null || $user === null) {
            return;
        }

        $delivered = $messenger->sendToUser($user, $broadcast->message, $broadcast->author, $broadcast);

        Broadcast::whereKey($broadcast->id)
            ->increment($delivered ? 'sent_count' : 'failed_count');

        // Пауза между сообщениями: Telegram ограничивает бота ~30 msg/sec.
        usleep(max(0, (int) config('telegram.broadcast.delay_ms', 40)) * 1000);
    }
}
