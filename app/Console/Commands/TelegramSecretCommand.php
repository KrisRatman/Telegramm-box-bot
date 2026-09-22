<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class TelegramSecretCommand extends Command
{
    protected $signature = 'telegram:secret';

    protected $description = 'Сгенерировать значение для TELEGRAM_WEBHOOK_SECRET';

    public function handle(): int
    {
        // Telegram разрешает в secret_token только A-Z, a-z, 0-9, _ и -.
        $secret = Str::random(48);

        $this->components->info('Скопируйте строку в .env:');
        $this->line("TELEGRAM_WEBHOOK_SECRET={$secret}");

        return self::SUCCESS;
    }
}
