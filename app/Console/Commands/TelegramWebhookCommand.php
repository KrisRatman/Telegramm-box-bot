<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\SelectsBots;
use App\Models\Bot;
use App\Telegram\BotManager;
use Illuminate\Console\Command;
use Throwable;

/**
 * Управление webhook одной командой, чтобы не дёргать Bot API руками.
 * У каждого бота свой адрес (APP_URL/telegram/webhook/{id}) и свой секрет.
 */
class TelegramWebhookCommand extends Command
{
    use SelectsBots;

    protected $signature = 'telegram:webhook
                            {action=set : set, info или remove}
                            {--bot= : id бота; без опции — все активные боты}';

    protected $description = 'Установить, показать или удалить webhook ботов';

    public function handle(BotManager $manager): int
    {
        $action = $this->argument('action');

        if (! in_array($action, ['set', 'info', 'remove'], true)) {
            $this->error('Допустимые действия: set, info, remove.');

            return self::FAILURE;
        }

        $bots = $this->selectedBots();
        $ok = $bots->isNotEmpty();

        foreach ($bots as $bot) {
            $this->components->twoColumnDetail("<fg=cyan>{$bot->name}</>", "id {$bot->id}");

            try {
                $ok = match ($action) {
                    'set' => $this->set($manager, $bot),
                    'info' => $this->info_($manager, $bot),
                    'remove' => $this->remove($manager, $bot),
                } && $ok;
            } catch (Throwable $e) {
                $this->error('  Telegram отклонил запрос: '.$e->getMessage());
                $ok = false;
            }
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    private function set(BotManager $manager, Bot $bot): bool
    {
        $url = $bot->webhookUrl();

        if (! str_starts_with($url, 'https://')) {
            $this->error("  Telegram принимает только HTTPS. Получено: {$url}");
            $this->line('  Укажите в APP_URL https-адрес; локально подойдёт туннель: cloudflared tunnel --url http://localhost:8000');

            return false;
        }

        $manager->for($bot)->setWebhook(url: $url, secret_token: $bot->webhook_secret, drop_pending_updates: true);
        $this->components->info("Webhook установлен: {$url}");

        return true;
    }

    private function info_(BotManager $manager, Bot $bot): bool
    {
        $info = $manager->for($bot)->getWebhookInfo();

        $this->components->twoColumnDetail('  URL', $info?->url ?: '— не установлен —');
        $this->components->twoColumnDetail('  Ожидают обработки', (string) ($info?->pending_update_count ?? 0));
        $this->components->twoColumnDetail('  Последняя ошибка', $info?->last_error_message ?: '—');

        return true;
    }

    private function remove(BotManager $manager, Bot $bot): bool
    {
        $manager->for($bot)->deleteWebhook();
        $this->components->info('Webhook удалён.');

        return true;
    }
}
