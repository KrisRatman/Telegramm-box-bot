<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Service;
use App\Models\TelegramUser;
use App\Services\Telegram\BotMessenger;
use App\Telegram\Support\Texts;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(private readonly BotMessenger $messenger) {}

    /**
     * Создаёт заявку из бота и сразу уведомляет администраторов.
     */
    public function createFromBot(
        TelegramUser $user,
        Service $service,
        string $contactName,
        string $contactPhone,
        ?string $comment = null,
    ): Order {
        $order = DB::transaction(function () use ($user, $service, $contactName, $contactPhone, $comment) {
            return Order::create([
                'telegram_user_id' => $user->id,
                'service_id' => $service->id,
                // Название и цену фиксируем на момент заявки: каталог может измениться.
                'service_name' => $service->name,
                'price' => $service->price,
                'status' => OrderStatus::New,
                'contact_name' => $contactName,
                'contact_phone' => $contactPhone,
                'comment' => $comment,
            ]);
        });

        // Телефон из заявки пригодится в карточке пользователя в админке.
        if ($user->phone !== $contactPhone) {
            $user->forceFill(['phone' => $contactPhone])->save();
        }

        $this->messenger->notifyAdmins(Texts::newOrderForAdmin($order->load('telegramUser')));

        return $order;
    }

    /**
     * Смена статуса. Уведомление клиенту шлёт OrderObserver, поэтому оно
     * уходит и при правке заявки через форму редактирования.
     */
    public function changeStatus(Order $order, OrderStatus $status): Order
    {
        if ($order->status === $status) {
            return $order;
        }

        $order->status = $status;
        $order->completed_at = $status === OrderStatus::Completed ? now() : null;
        $order->save();

        return $order;
    }
}
