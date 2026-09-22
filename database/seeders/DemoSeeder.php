<?php

namespace Database\Seeders;

use App\Enums\MessageDirection;
use App\Enums\OrderStatus;
use App\Models\BotMessage;
use App\Models\Order;
use App\Models\Service;
use App\Models\TelegramUser;
use Illuminate\Database\Seeder;

/**
 * Демо-данные для скриншотов и показа админки заказчику.
 * В продакшене не запускается: php artisan db:seed --class=DemoSeeder.
 */
class DemoSeeder extends Seeder
{
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

                Order::create([
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
            }
        }
    }
}
