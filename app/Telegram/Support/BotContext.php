<?php

namespace App\Telegram\Support;

use App\Models\TelegramUser;
use RuntimeException;
use SergiX44\Nutgram\Nutgram;

class BotContext
{
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
