<?php

namespace Tests\Concerns;

use GuzzleHttp\Psr7\Request;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Chat\Chat;
use SergiX44\Nutgram\Telegram\Types\User\User;
use SergiX44\Nutgram\Testing\FakeNutgram;

/**
 * В тестах контейнер отдаёт Nutgram::fake(), поэтому запросы к Bot API
 * никуда не уходят, а ответы бота можно проверять ассертами.
 */
trait InteractsWithBot
{
    protected function fakeBot(int $chatId = 424242, string $firstName = 'Иван'): Nutgram
    {
        $user = new User;
        $user->id = $chatId;
        $user->is_bot = false;
        $user->first_name = $firstName;
        $user->last_name = 'Петров';
        $user->username = 'ivan';
        $user->language_code = 'ru';

        $chat = new Chat;
        $chat->id = $chatId;
        $chat->type = 'private';
        $chat->first_name = $firstName;

        return app(Nutgram::class)
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
