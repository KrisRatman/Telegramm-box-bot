<?php

namespace App\Telegram\Middleware;

use App\Enums\MessageDirection;
use App\Models\BotMessage;
use App\Models\TelegramUser;
use App\Telegram\Support\BotContext;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\UpdateType;

/**
 * Глобальная middleware: на каждом апдейте заводит или обновляет запись
 * пользователя в пределах бота, кладёт её в контекст апдейта под ключом
 * telegram_user, включает язык пользователя и пишет входящие сообщения
 * в историю переписки.
 */
class TrackTelegramUser
{
    public function __invoke(Nutgram $bot, $next): void
    {
        $from = $bot->user();

        if ($from === null || $from->is_bot) {
            $next($bot);

            return;
        }

        $user = TelegramUser::updateOrCreate(
            ['bot_id' => BotContext::bot($bot)->id, 'chat_id' => $from->id],
            [
                'username' => $from->username,
                'first_name' => $from->first_name,
                'last_name' => $from->last_name,
                'language_code' => $from->language_code ?: 'ru',
                'last_activity_at' => now(),
                // Раз пользователь пишет — бот у него точно не заблокирован.
                'is_blocked' => false,
            ],
        );

        $bot->set('telegram_user', $user);

        // Все тексты и кнопки ответа — на языке пользователя.
        app()->setLocale($user->preferredLocale());

        $this->logIncoming($bot, $user);

        $next($bot);
    }

    private function logIncoming(Nutgram $bot, TelegramUser $user): void
    {
        // На нажатие инлайн-кнопки $bot->message() отдаёт сообщение бота,
        // к которому кнопка прикреплена. Пишем только настоящие входящие.
        if ($bot->update()?->getType() !== UpdateType::MESSAGE) {
            return;
        }

        $message = $bot->message();
        $text = $message?->text;

        if ($text === null || $text === '' || $message?->from?->is_bot) {
            return;
        }

        BotMessage::create([
            'telegram_user_id' => $user->id,
            'direction' => MessageDirection::In,
            'text' => $text,
            'telegram_message_id' => $message->message_id,
        ]);
    }
}
