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
            ->with('items.service')
            ->latest()
            ->limit(10)
            ->get();

        $botModel = BotContext::bot($bot);
        $text = $orders->isEmpty() ? Texts::noOrders() : Texts::orders($orders, $botModel);

        Screen::show($bot, $text, Keyboards::myOrders($orders, $botModel));
    }
}
