<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'status' => PaymentStatus::Pending,
            'amount' => fake()->numberBetween(1, 100) * 500,
            'currency' => 'RUB',
        ];
    }

    public function forOrder(Order $order): static
    {
        return $this->state(fn () => [
            'order_id' => $order->id,
            'amount' => $order->price,
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::Paid,
            'telegram_payment_charge_id' => 'tg_'.fake()->unique()->bothify('##########'),
            'provider_payment_charge_id' => fake()->uuid(),
            'paid_at' => now(),
        ]);
    }
}
