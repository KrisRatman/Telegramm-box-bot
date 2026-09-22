<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use SergiX44\Nutgram\Nutgram;
use Throwable;

/**
 * Управление webhook одной командой, чтобы не дёргать Bot API руками.
 */
class TelegramWebhookCommand extends Command
{
    protected $signature = 'telegram:webhook
                            {action=set : set, info или remove}
                            {--url= : Публичный URL; по умолчанию APP_URL + /telegram/webhook}';

    protected $description = 'Установить, показать или удалить webhook Telegram-бота';

    public function handle(Nutgram $bot): int
    {
        return match ($this->argument('action')) {
            'set' => $this->set($bot),
            'info' => $this->info_($bot),
            'remove' => $this->remove($bot),
            default => $this->invalidAction(),
        };
    }

    private function set(Nutgram $bot): int
    {
        $secret = (string) config('telegram.webhook_secret');

        if ($secret === '') {
            $this->error('TELEGRAM_WEBHOOK_SECRET пуст. Сгенерируйте значение: php artisan telegram:secret');

            return self::FAILURE;
        }

        $url = $this->option('url') ?: rtrim((string) config('app.url'), '/').'/telegram/webhook';

        if (! str_starts_with($url, 'https://')) {
            $this->error("Telegram принимает только HTTPS. Получено: {$url}");
            $this->line('Для локальной разработки поднимите туннель, например: ngrok http 8000');

            return self::FAILURE;
        }

        try {
            $bot->setWebhook(url: $url, secret_token: $secret, drop_pending_updates: true);
        } catch (Throwable $e) {
            $this->error('Telegram отклонил запрос: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Webhook установлен: {$url}");

        return self::SUCCESS;
    }

    private function info_(Nutgram $bot): int
    {
        try {
            $info = $bot->getWebhookInfo();
        } catch (Throwable $e) {
            $this->error('Не удалось получить данные: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('URL', $info?->url ?: '— не установлен —');
        $this->components->twoColumnDetail('Ожидают обработки', (string) ($info?->pending_update_count ?? 0));
        $this->components->twoColumnDetail('Последняя ошибка', $info?->last_error_message ?: '—');

        return self::SUCCESS;
    }

    private function remove(Nutgram $bot): int
    {
        try {
            $bot->deleteWebhook();
        } catch (Throwable $e) {
            $this->error('Не удалось удалить webhook: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Webhook удалён.');

        return self::SUCCESS;
    }

    private function invalidAction(): int
    {
        $this->error('Допустимые действия: set, info, remove.');

        return self::FAILURE;
    }
}
