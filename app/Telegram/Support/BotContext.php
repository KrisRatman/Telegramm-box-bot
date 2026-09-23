<?php

namespace App\Telegram\Support;

use App\Models\Bot;
use App\Models\TelegramUser;
use App\Telegram\BotManager;
use RuntimeException;
use SergiX44\Nutgram\Nutgram;

class BotContext
{
    /**
     * Бот, в который пришёл апдейт.
     */
    public static function bot(Nutgram $bot): Bot
    {
        return app(BotManager::class)->botOf($bot);
    }

    /**
     * Пользователь текущего апдейта — его кладёт TrackTelegramUser.
     */
    public static function user(Nutgram $bot): TelegramUser
    {
        $user = $bot->get('telegram_user');

        if (! $user instanceof TelegramUser) {
            throw new RuntimeException('Пользователь Telegram не определён для этого апдейта.');
        }

        return $user;
    }
}
