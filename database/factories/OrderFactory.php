<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Service;
use App\Models\TelegramUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        $service = Service::factory();

        return [
            'telegram_user_id' => TelegramUser::factory(),
            'service_id' => $service,
            'service_name' => fake()->words(3, true),
            'price' => fake()->numberBetween(1, 100) * 500,
            'status' => OrderStatus::New,
            'contact_name' => fake()->firstName(),
            'contact_phone' => '+7900'.fake()->numerify('#######'),
            'comment' => null,
        ];
    }

    public function status(OrderStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
