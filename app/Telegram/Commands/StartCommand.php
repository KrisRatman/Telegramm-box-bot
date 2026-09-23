<?php

namespace App\Telegram\Commands;

use App\Telegram\Support\BotContext;
use App\Telegram\Support\Keyboards;
use App\Telegram\Support\Screen;
use App\Telegram\Support\Texts;
use SergiX44\Nutgram\Nutgram;

class StartCommand
{
    public function __invoke(Nutgram $bot): void
    {
        Screen::show($bot, Texts::greeting(BotContext::user($bot)), Keyboards::mainMenu(BotContext::bot($bot)));
    }
}
