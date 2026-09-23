<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\SelectsBots;
use App\Telegram\BotManager;
use App\Telegram\Support\Texts;
use Illuminate\Console\Command;
use SergiX44\Nutgram\Telegram\Types\Command\MenuButtonDefault;
use SergiX44\Nutgram\Telegram\Types\Command\MenuButtonWebApp;
use SergiX44\Nutgram\Telegram\Types\WebApp\WebAppInfo;
use Throwable;

/**
 * Кнопка слева от поля ввода. По умолчанию там список команд,
 * после set — открывается Mini App бота.
 */
class TelegramMenuButtonCommand extends Command
{
    use SelectsBots;

    protected $signature = 'telegram:menu-button
                            {action=set : set — открывать Mini App, reset — вернуть список команд}
                            {--bot= : id бота; без опции — все активные боты}';

    protected $description = 'Поставить Mini App на кнопку меню ботов';

    public function handle(BotManager $manager): int
    {
        $action = $this->argument('action');

        if (! in_array($action, ['set', 'reset'], true)) {
            $this->error('Допустимые действия: set, reset.');

            return self::FAILURE;
        }

        $bots = $this->selectedBots();
        $ok = $bots->isNotEmpty();

        foreach ($bots as $bot) {
            $url = $bot->miniAppUrl();

            if ($action === 'set' && $url === null) {
                $this->error("{$bot->name}: укажите в TELEGRAM_MINI_APP_URL https-адрес сайта.");
                $ok = false;

                continue;
            }

            try {
                // Подпись кнопки — на языке бота по умолчанию.
                $label = Texts::in($bot->default_locale, fn () => __('bot.menu_button'));

                $manager->for($bot)->setChatMenuButton(menu_button: $action === 'set'
                    ? new MenuButtonWebApp($label, WebAppInfo::make($url))
                    : new MenuButtonDefault);
            } catch (Throwable $e) {
                $this->error("{$bot->name}: Telegram отклонил запрос: ".$e->getMessage());
                $ok = false;

                continue;
            }

            $this->components->info($action === 'set'
                ? "{$bot->name}: кнопка меню открывает {$url}"
                : "{$bot->name}: кнопка меню снова показывает команды.");
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
