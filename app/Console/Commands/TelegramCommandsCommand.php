<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\SelectsBots;
use App\Telegram\BotManager;
use App\Telegram\Support\Texts;
use Illuminate\Console\Command;
use SergiX44\Nutgram\Telegram\Types\Command\BotCommand;
use Throwable;

/**
 * Меню команд у поля ввода на каждом языке. Telegram сам показывает
 * вариант под язык клиента; для остальных — язык бота по умолчанию.
 */
class TelegramCommandsCommand extends Command
{
    use SelectsBots;

    private const COMMANDS = ['start', 'help', 'orders', 'language'];

    protected $signature = 'telegram:commands {--bot= : id бота; без опции — все активные боты}';

    protected $description = 'Зарегистрировать меню команд ботов на всех языках';

    public function handle(BotManager $manager): int
    {
        $bots = $this->selectedBots();
        $ok = $bots->isNotEmpty();

        foreach ($bots as $bot) {
            try {
                $nutgram = $manager->for($bot);

                foreach (config('telegram.locales') as $locale) {
                    $nutgram->setMyCommands($this->commands($locale), language_code: $locale);
                }

                // Для языков без перевода — меню на языке бота.
                $nutgram->setMyCommands($this->commands($bot->default_locale));
            } catch (Throwable $e) {
                $this->error("{$bot->name}: Telegram отклонил запрос: ".$e->getMessage());
                $ok = false;

                continue;
            }

            $this->components->info("{$bot->name}: меню команд обновлено.");
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return list<BotCommand>
     */
    private function commands(string $locale): array
    {
        return Texts::in($locale, fn () => array_map(
            fn (string $command) => BotCommand::make($command, __("bot.commands.{$command}")),
            self::COMMANDS,
        ));
    }
}
