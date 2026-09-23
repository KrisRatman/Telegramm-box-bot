<?php

namespace Database\Factories;

use App\Models\Bot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bot>
 */
class BotFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Бот '.fake()->unique()->city(),
            'username' => fake()->unique()->userName().'_bot',
            'token' => fake()->unique()->numberBetween(100000000, 999999999).':'.fake()->regexify('[A-Za-z0-9_-]{35}'),
            'payment_provider_token' => null,
            'default_locale' => 'ru',
            'is_active' => true,
        ];
    }

    public function withPayments(): static
    {
        return $this->state(fn () => ['payment_provider_token' => 'test-provider-token']);
    }
}
