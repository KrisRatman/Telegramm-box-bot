<?php

namespace Tests\Concerns;

use App\Models\Bot;
use App\Telegram\BotManager;
use GuzzleHttp\Psr7\Request;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Chat\Chat;
use SergiX44\Nutgram\Telegram\Types\User\User;
use SergiX44\Nutgram\Testing\FakeNutgram;

/**
 * В тестах контейнер отдаёт Nutgram::fake(), поэтому запросы к Bot API
 * никуда не уходят, а ответы бота можно проверять ассертами.
 * BotManager в тестах отдаёт этот же fake для любого бота.
 */
trait InteractsWithBot
{
    /**
     * Бот, от имени которого идут апдейты: первый в базе или новый.
     */
    protected function testBot(): Bot
    {
        return Bot::query()->orderBy('id')->first() ?? Bot::factory()->create();
    }

    protected function fakeBot(int $chatId = 424242, string $firstName = 'Иван', ?Bot $bot = null, string $languageCode = 'ru'): Nutgram
    {
        $user = new User;
        $user->id = $chatId;
        $user->is_bot = false;
        $user->first_name = $firstName;
        $user->last_name = 'Петров';
        $user->username = 'ivan';
        $user->language_code = $languageCode;

        $chat = new Chat;
        $chat->id = $chatId;
        $chat->type = 'private';
        $chat->first_name = $firstName;

        // Привязываем fake к боту: обработчики узнают по нему, чей это апдейт.
        return app(BotManager::class)->for($bot ?? $this->testBot())
            ->setCommonUser($user)
            ->setCommonChat($chat);
    }

    /**
     * Тело исходящего запроса к Bot API в виде массива.
     *
     * @return array<string, mixed>
     */
    protected function payload(Request $request): array
    {
        return FakeNutgram::getActualData($request);
    }

    /**
     * Текст сообщения, которое бот отправил в ответ.
     */
    protected function replyText(Request $request): string
    {
        return (string) ($this->payload($request)['text'] ?? '');
    }

    /**
     * Тела всех запросов к указанному методу Bot API за последний апдейт.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function requestsTo(Nutgram $bot, string $method): array
    {
        return collect($bot->getRequestHistory())
            ->map(fn (array $reqRes) => array_values($reqRes)[0])
            ->filter(fn (Request $request) => $request->getUri()->getPath() === $method)
            ->map(fn (Request $request) => $this->payload($request))
            ->values()
            ->all();
    }

    /**
     * Подписи инлайн-кнопок из ответа бота.
     *
     * @return array<int, string>
     */
    protected function replyButtons(Request $request): array
    {
        $markup = $this->payload($request)['reply_markup'] ?? '{}';

        if (is_string($markup)) {
            $markup = json_decode($markup, true) ?: [];
        }

        return collect($markup['inline_keyboard'] ?? [])
            ->flatten(1)
            ->pluck('text')
            ->all();
    }
}
