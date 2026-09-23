<?php

namespace App\Telegram\Handlers;

use App\Telegram\Support\BotContext;
use App\Telegram\Support\Keyboards;
use App\Telegram\Support\Screen;
use App\Telegram\Support\Texts;
use SergiX44\Nutgram\Nutgram;

class MenuHandler
{
    public function main(Nutgram $bot): void
    {
        Screen::show($bot, Texts::mainMenu(), Keyboards::mainMenu(BotContext::bot($bot)));
    }

    public function help(Nutgram $bot): void
    {
        Screen::show($bot, Texts::help(BotContext::bot($bot)), Keyboards::backToMenu());
    }
}
