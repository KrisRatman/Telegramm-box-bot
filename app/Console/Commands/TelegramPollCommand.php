<?php

namespace App\Console\Commands;

use App\Models\Bot;
use App\Telegram\BotManager;
use App\Telegram\Support\ResilientPolling;
use Illuminate\Console\Command;

/**
 * Long polling для одного бота — когда webhook некуда направить
 * (локальная разработка без туннеля). getUpdates блокирует процесс,
 * поэтому на каждого бота — свой процесс: telegram:poll 1, telegram:poll 2.
 */
class TelegramPollCommand extends Command
{
    protected $signature = 'telegram:poll {bot? : id бота; по умолчанию — первый активный}';

    protected $description = 'Запустить бота в режиме long polling';

    public function handle(BotManager $manager): int
    {
        $query = Bot::query()->active()->orderBy('id');
        $bot = $this->argument('bot') ? $query->find((int) $this->argument('bot')) : $query->first();

        if ($bot === null) {
            $this->error('Активный бот не найден. Добавьте бота с токеном в админке.');

            return self::FAILURE;
        }

        $this->components->info("Бот «{$bot->name}» (id {$bot->id}) слушает обновления. Остановить — Ctrl+C.");

        $nutgram = $manager->for($bot);
        // Polling и webhook у одного бота не работают одновременно.
        $nutgram->deleteWebhook();
        $nutgram->setRunningMode(ResilientPolling::class);
        $nutgram->run();

        return self::SUCCESS;
    }
}
