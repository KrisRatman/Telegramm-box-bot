<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Service;
use App\Models\TelegramUser;
use App\Services\Telegram\BotMessenger;
use App\Telegram\Support\Keyboards;
use App\Telegram\Support\Texts;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(private readonly BotMessenger $messenger) {}

    /**
     * Заявка на одну услугу из диалога в боте.
     */
    public function createFromBot(
        TelegramUser $user,
        Service $service,
        string $contactName,
        string $contactPhone,
        ?string $comment = null,
    ): Order {
        return $this->create($user, collect([['service' => $service, 'quantity' => 1]]), $contactName, $contactPhone, $comment);
    }

    /**
     * Заявка из корзины Mini App. Клиент сообщает только id услуг и количество,
     * цены берём из каталога — присланным с фронта суммам верить нельзя.
     *
     * @param  array<int, int>  $quantities  service_id => количество
     */
    public function createFromCart(
        TelegramUser $user,
        array $quantities,
        string $contactName,
        string $contactPhone,
        ?string $comment = null,
    ): Order {
        $services = Service::query()->orderable()->findMany(array_keys($quantities));

        // Услугу могли снять с продажи, пока клиент держал её в корзине.
        if ($services->count() !== count($quantities)) {
            throw ValidationException::withMessages([
                'items' => 'Часть услуг больше недоступна. Обновите каталог и проверьте корзину.',
            ]);
        }

        $lines = $services->map(fn (Service $service) => [
            'service' => $service,
            'quantity' => $quantities[$service->id],
        ]);

        $order = $this->create($user, $lines, $contactName, $contactPhone, $comment);

        // Клиент оформлял заявку в Mini App — подтверждение дублируем в чат,
        // чтобы номер и состав остались в переписке.
        $this->messenger->sendToUser($user, Texts::orderCreated($order), keyboard: Keyboards::orderCreated($order));

        return $order;
    }

    /**
     * @param  Collection<int, array{service: Service, quantity: int}>  $lines
     */
    private function create(
        TelegramUser $user,
        Collection $lines,
        string $contactName,
        string $contactPhone,
        ?string $comment,
    ): Order {
        $order = DB::transaction(function () use ($user, $lines, $contactName, $contactPhone, $comment) {
            $first = $lines->first()['service'];

            $order = Order::create([
                'telegram_user_id' => $user->id,
                // Одна услуга — ссылка на неё, для корзины в списке заявок хватит сводки.
                'service_id' => $lines->count() === 1 ? $first->id : null,
                'service_name' => $this->summary($lines),
                'price' => $lines->sum(fn (array $line) => (float) $line['service']->price * $line['quantity']),
                'status' => OrderStatus::New,
                'contact_name' => $contactName,
                'contact_phone' => $contactPhone,
                'comment' => $comment,
            ]);

            // Название и цену фиксируем на момент заявки: каталог может измениться.
            $order->items()->createMany($lines->map(fn (array $line) => [
                'service_id' => $line['service']->id,
                'service_name' => $line['service']->name,
                'price' => $line['service']->price,
                'quantity' => $line['quantity'],
            ]));

            return $order;
        });

        // Телефон из заявки пригодится в карточке пользователя в админке.
        if ($user->phone !== $contactPhone) {
            $user->forceFill(['phone' => $contactPhone])->save();
        }

        $this->messenger->notifyAdmins(Texts::newOrderForAdmin($order->load('telegramUser', 'items')));

        return $order;
    }

    /**
     * «Сайт-визитка» или «Сайт-визитка и ещё 2» — для списков и счёта.
     *
     * @param  Collection<int, array{service: Service, quantity: int}>  $lines
     */
    private function summary(Collection $lines): string
    {
        $name = $lines->first()['service']->name;
        $rest = $lines->count() - 1;

        return $rest > 0 ? mb_substr($name, 0, 200)." и ещё {$rest}" : $name;
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
