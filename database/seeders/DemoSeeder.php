<?php

namespace Database\Seeders;

use App\Enums\MessageDirection;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\BotMessage;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Service;
use App\Models\TelegramUser;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

/**
 * Демо-данные для скриншотов и показа админки заказчику.
 * В продакшене не запускается: php artisan db:seed --class=DemoSeeder.
 */
class DemoSeeder extends Seeder
{
    private int $payableOrders = 0;

    public function run(): void
    {
        $services = Service::query()->active()->get();

        if ($services->isEmpty()) {
            $this->call(CatalogSeeder::class);
            $services = Service::query()->active()->get();
        }

        $people = [
            ['Анна', 'Смирнова', 'anna_s', '+79001112233'],
            ['Дмитрий', 'Ковалёв', 'dkovalev', '+79262223344'],
            ['Ольга', 'Иванова', null, '+79153334455'],
            ['Сергей', 'Петров', 'spetrov', '+79094445566'],
            ['Мария', 'Новикова', 'mnovikova', '+79165556677'],
        ];

        $statuses = OrderStatus::cases();

        foreach ($people as $index => [$first, $last, $username, $phone]) {
            $user = TelegramUser::updateOrCreate(
                ['chat_id' => 700000000 + $index],
                [
                    'first_name' => $first,
                    'last_name' => $last,
                    'username' => $username,
                    'phone' => $phone,
                    'language_code' => 'ru',
                    'is_blocked' => false,
                    'last_activity_at' => now()->subHours($index * 5),
                ],
            );

            BotMessage::create([
                'telegram_user_id' => $user->id,
                'direction' => MessageDirection::In,
                'text' => '/start',
                'created_at' => now()->subDays($index + 1),
                'updated_at' => now()->subDays($index + 1),
            ]);

            foreach (range(0, $index % 3) as $orderIndex) {
                $service = $services->random();
                $status = $statuses[($index + $orderIndex) % count($statuses)];

                $order = Order::create([
                    'telegram_user_id' => $user->id,
                    'service_id' => $service->id,
                    'service_name' => $service->name,
                    'price' => $service->price,
                    'status' => $status,
                    'contact_name' => $first,
                    'contact_phone' => $phone,
                    'comment' => $orderIndex === 0 ? 'Удобнее созвониться после 18:00.' : null,
                    'completed_at' => $status === OrderStatus::Completed ? now()->subDays($index) : null,
                    'created_at' => now()->subDays($index)->subHours($orderIndex),
                    'updated_at' => now()->subDays($index),
                ]);

                $order->items()->create([
                    'service_id' => $service->id,
                    'service_name' => $service->name,
                    'price' => $service->price,
                    'quantity' => 1,
                ]);

                $this->seedPayment($order);
            }

            if ($index === 0) {
                $this->seedCartOrder($user, $services->take(3), $phone);
            }
        }
    }

    /**
     * Заявка из корзины Mini App: несколько услуг в одной заявке.
     *
     * @param  Collection<int, Service>  $services
     */
    private function seedCartOrder(TelegramUser $user, Collection $services, string $phone): void
    {
        $quantities = $services->values()->mapWithKeys(fn (Service $service, int $i) => [$service->id => $i === 0 ? 2 : 1]);

        $order = Order::create([
            'telegram_user_id' => $user->id,
            'service_id' => null,
            'service_name' => $services->first()->name.' и ещё '.($services->count() - 1),
            'price' => $services->sum(fn (Service $service) => (float) $service->price * $quantities[$service->id]),
            'status' => OrderStatus::New,
            'contact_name' => $user->first_name,
            'contact_phone' => $phone,
            'comment' => 'Оформлено через Mini App.',
        ]);

        foreach ($services as $service) {
            $order->items()->create([
                'service_id' => $service->id,
                'service_name' => $service->name,
                'price' => $service->price,
                'quantity' => $quantities[$service->id],
            ]);
        }
    }

    /**
     * Часть подтверждённых и выполненных заявок оплачена в боте,
     * одна — с возвратом, чтобы в админке были все состояния платежа.
     */
    private function seedPayment(Order $order): void
    {
        $payable = in_array($order->status, [OrderStatus::Confirmed, OrderStatus::InProgress, OrderStatus::Completed], true);

        if (! $payable) {
            return;
        }

        // Каждая четвёртая подходящая заявка остаётся неоплаченной, третья — с возвратом.
        $position = ++$this->payableOrders;

        if ($position % 4 === 0) {
            return;
        }

        $paidAt = $order->created_at->copy()->addHour();
        $refunded = $position === 3;

        // forceCreate — чтобы сохранились исторические created_at и updated_at.
        Payment::query()->forceCreate([
            'order_id' => $order->id,
            'status' => $refunded ? PaymentStatus::Refunded : PaymentStatus::Paid,
            'amount' => $order->price,
            'currency' => 'RUB',
            'telegram_payment_charge_id' => 'demo_tg_'.$order->id,
            'provider_payment_charge_id' => 'demo-'.str_pad((string) $order->id, 8, '0', STR_PAD_LEFT),
            'paid_at' => $paidAt,
            'refunded_at' => $refunded ? $paidAt->copy()->addDay() : null,
            'created_at' => $order->created_at,
            'updated_at' => $paidAt,
        ]);

        if (! $refunded) {
            $order->forceFill(['paid_at' => $paidAt])->saveQuietly();
        }
    }
}
