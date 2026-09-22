<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\Telegram\BotMessenger;
use App\Telegram\Support\Texts;

/**
 * Клиента уведомляем из одной точки: статус меняют и кнопкой в списке,
 * и формой редактирования, и кодом — сообщение должно уйти в любом случае.
 */
class OrderObserver
{
    public function __construct(private readonly BotMessenger $messenger) {}

    public function updated(Order $order): void
    {
        if (! $order->wasChanged('status')) {
            return;
        }

        $order->loadMissing('telegramUser');

        if ($order->telegramUser === null) {
            return;
        }

        $this->messenger->sendToUser($order->telegramUser, Texts::statusChanged($order));
    }
}
