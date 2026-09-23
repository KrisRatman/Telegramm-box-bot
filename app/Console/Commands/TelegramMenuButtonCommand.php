<?php

namespace App\Console\Commands;

use App\Telegram\Support\Keyboards;
use Illuminate\Console\Command;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Command\MenuButtonDefault;
use SergiX44\Nutgram\Telegram\Types\Command\MenuButtonWebApp;
use SergiX44\Nutgram\Telegram\Types\WebApp\WebAppInfo;
use Throwable;

/**
 * Кнопка слева от поля ввода. По умолчанию там список команд,
 * после set — открывается Mini App из TELEGRAM_MINI_APP_URL.
 */
class TelegramMenuButtonCommand extends Command
{
    protected $signature = 'telegram:menu-button
                            {action=set : set — открывать Mini App, reset — вернуть список команд}';

    protected $description = 'Поставить Mini App на кнопку меню бота';

    public function handle(Nutgram $bot): int
    {
        $action = $this->argument('action');

        if (! in_array($action, ['set', 'reset'], true)) {
            $this->error('Допустимые действия: set, reset.');

            return self::FAILURE;
        }

        $url = Keyboards::miniAppUrl();

        if ($action === 'set' && $url === null) {
            $this->error('Укажите в TELEGRAM_MINI_APP_URL https-адрес страницы /app.');

            return self::FAILURE;
        }

        try {
            $bot->setChatMenuButton(menu_button: $action === 'set'
                ? new MenuButtonWebApp('Каталог', WebAppInfo::make($url))
                : new MenuButtonDefault);
        } catch (Throwable $e) {
            $this->error('Telegram отклонил запрос: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->components->info($action === 'set' ? "Кнопка меню открывает {$url}" : 'Кнопка меню снова показывает команды.');

        return self::SUCCESS;
    }
}
