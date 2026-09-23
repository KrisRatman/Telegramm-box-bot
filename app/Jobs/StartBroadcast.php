<?php

namespace App\Jobs;

use App\Enums\BroadcastStatus;
use App\Models\Broadcast;
use App\Models\TelegramUser;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Throwable;

/**
 * Готовит пакет задач на отправку: собирает получателей и ставит
 * по задаче на каждого. Итоговый статус проставляют колбэки пакета.
 */
class StartBroadcast implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $broadcastId) {}

    public function handle(): void
    {
        $broadcast = Broadcast::find($this->broadcastId);

        if ($broadcast === null || $broadcast->status !== BroadcastStatus::Queued) {
            return;
        }

        $recipients = TelegramUser::query()
            ->where('bot_id', $broadcast->bot_id)
            ->subscribed()
            ->pluck('id');

        $broadcast->forceFill([
            'status' => BroadcastStatus::Sending,
            'recipients_count' => $recipients->count(),
            'sent_count' => 0,
            'failed_count' => 0,
            'started_at' => now(),
        ])->save();

        if ($recipients->isEmpty()) {
            $broadcast->forceFill([
                'status' => BroadcastStatus::Sent,
                'finished_at' => now(),
            ])->save();

            return;
        }

        $jobs = $recipients
            ->map(fn (int $userId) => new SendBroadcastMessage($broadcast->id, $userId))
            ->all();

        $broadcastId = $broadcast->id;

        Bus::batch($jobs)
            ->name("Рассылка #{$broadcastId}")
            ->allowFailures()
            ->then(function () use ($broadcastId) {
                Broadcast::whereKey($broadcastId)->update([
                    'status' => BroadcastStatus::Sent,
                    'finished_at' => now(),
                ]);
            })
            ->catch(function (mixed $batch, Throwable $e) use ($broadcastId) {
                Broadcast::whereKey($broadcastId)->update([
                    'status' => BroadcastStatus::Failed,
                    'finished_at' => now(),
                ]);
            })
            ->dispatch();
    }

    public function failed(?Throwable $exception): void
    {
        Broadcast::whereKey($this->broadcastId)->update([
            'status' => BroadcastStatus::Failed,
            'finished_at' => now(),
        ]);
    }
}
