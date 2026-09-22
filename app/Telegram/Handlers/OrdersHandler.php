<?php

namespace App\Telegram\Handlers;

use App\Telegram\Support\BotContext;
use App\Telegram\Support\Keyboards;
use App\Telegram\Support\Screen;
use App\Telegram\Support\Texts;
use SergiX44\Nutgram\Nutgram;

class OrdersHandler
{
    public function my(Nutgram $bot): void
    {
        $orders = BotContext::user($bot)
            ->orders()
            ->latest()
            ->limit(10)
            ->get();

        $text = $orders->isEmpty() ? Texts::noOrders() : Texts::orders($orders);

        Screen::show($bot, $text, Keyboards::backToMenu());
    }
}
