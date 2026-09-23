<?php

namespace App\Telegram;

use App\Models\Bot;
use Closure;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Foundation\Application;
use Psr\Log\LoggerInterface;
use RuntimeException;
use SergiX44\Nutgram\Configuration;
use SergiX44\Nutgram\Nutgram;
use WeakMap;

/**
 * Экземпляры Nutgram для ботов из таблицы bots.
 *
 * Пакет nutgram/laravel умеет только одного бота с токеном из .env.
 * Здесь то же самое, но на каждого бота: своя конфигурация с его токеном
 * и те же обработчики из routes/telegram.php. Экземпляр создаётся
 * один раз за процесс и переиспользуется.
 */
class BotManager
{
    /** @var array<int, Nutgram> */
    private array $instances = [];

    /** @var WeakMap<Nutgram, Bot> */
    private WeakMap $bots;

    /**
     * @param  (Closure(Bot): Nutgram)|null  $factory  Подмена сборки — для тестов с Nutgram::fake().
     */
    public function __construct(
        private readonly Application $app,
        private ?Closure $factory = null,
    ) {
        $this->bots = new WeakMap;
    }

    public function for(Bot $bot): Nutgram
    {
        if (! isset($this->instances[$bot->id])) {
            $nutgram = $this->factory !== null ? ($this->factory)($bot) : $this->make($bot);
            $this->instances[$bot->id] = $nutgram;
        }

        // Метку ставим при каждом вызове: в тестах все боты делят один fake-экземпляр.
        $this->bots[$this->instances[$bot->id]] = $bot;

        return $this->instances[$bot->id];
    }

    /**
     * Бот, которому принадлежит экземпляр Nutgram, — нужен обработчикам,
     * чтобы понять, в каком боте пришёл апдейт.
     */
    public function botOf(Nutgram $nutgram): Bot
    {
        return $this->bots[$nutgram] ??= $this->findByToken($nutgram)
            ?? throw new RuntimeException('Экземпляр Nutgram не привязан к боту.');
    }

    /**
     * Экземпляр создан не здесь — например, штатным nutgram:run с токеном из .env.
     * Ищем бота по числовой части токена: она совпадает с id бота в Telegram.
     */
    private function findByToken(Nutgram $nutgram): ?Bot
    {
        $telegramId = $nutgram->getBotId();

        return Bot::query()->active()->get()
            ->first(fn (Bot $bot) => str_starts_with((string) $bot->token, "{$telegramId}:"));
    }

    private function make(Bot $bot): Nutgram
    {
        if (blank($bot->token)) {
            throw new RuntimeException("У бота «{$bot->name}» не задан токен.");
        }

        $nutgram = new Nutgram($bot->token, new Configuration(
            clientTimeout: config('nutgram.config.timeout', Configuration::DEFAULT_CLIENT_TIMEOUT),
            container: $this->app,
            cache: $this->app->make(Cache::class),
            logger: $this->app->make(LoggerInterface::class)->channel(config('nutgram.log_channel', 'null')),
            pollingTimeout: config('nutgram.config.polling.timeout', Configuration::DEFAULT_POLLING_TIMEOUT),
            conversationTtl: config('nutgram.config.conversation_ttl', Configuration::DEFAULT_CONVERSATION_TTL),
        ));

        // Те же обработчики, что у пакета: файл ждёт переменную $bot.
        (function (Nutgram $bot) {
            require base_path('routes/telegram.php');
        })($nutgram);

        return $nutgram;
    }
}
