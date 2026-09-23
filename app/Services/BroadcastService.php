<?php

namespace App\Services;

use App\Enums\BroadcastStatus;
use App\Jobs\StartBroadcast;
use App\Models\Broadcast;
use App\Models\TelegramUser;

class BroadcastService
{
    /**
     * Ставит рассылку в очередь. Сама отправка идёт воркером,
     * поэтому админка не ждёт и не упирается в таймаут.
     */
    public function queue(Broadcast $broadcast): Broadcast
    {
        $broadcast->forceFill([
            'status' => BroadcastStatus::Queued,
            'recipients_count' => $this->recipientsCount($broadcast->bot_id),
            'sent_count' => 0,
            'failed_count' => 0,
            'started_at' => null,
            'finished_at' => null,
        ])->save();

        StartBroadcast::dispatch($broadcast->id);

        return $broadcast;
    }

    /**
     * Получатели — подписчики бота рассылки: пишет каждый бот только своим.
     */
    public function recipientsCount(?int $botId): int
    {
        return TelegramUser::query()
            ->when($botId, fn ($query) => $query->where('bot_id', $botId))
            ->subscribed()
            ->count();
    }
}
