<?php

namespace App\Telegram\Support;

use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

/**
 * Меню бота — один экран, который перерисовывается на месте.
 * По команде шлём новое сообщение, по нажатию кнопки — редактируем текущее.
 */
class Screen
{
    public static function show(Nutgram $bot, string $text, ?InlineKeyboardMarkup $keyboard = null): void
    {
        if ($bot->callbackQuery() !== null) {
            $bot->editMessageText(
                text: $text,
                parse_mode: ParseMode::HTML,
                reply_markup: $keyboard,
            );

            $bot->answerCallbackQuery();

            return;
        }

        $bot->sendMessage(
            text: $text,
            parse_mode: ParseMode::HTML,
            reply_markup: $keyboard,
        );
    }
}
