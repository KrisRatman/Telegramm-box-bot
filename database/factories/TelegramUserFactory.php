<?php

namespace Database\Factories;

use App\Models\Bot;
use App\Models\TelegramUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TelegramUser>
 */
class TelegramUserFactory extends Factory
{
    public function definition(): array
    {
        return [
            // Первый бот в базе или новый: в тестах с одним ботом все записи попадают в него.
            'bot_id' => fn () => Bot::query()->value('id') ?? Bot::factory(),
            'chat_id' => fake()->unique()->numberBetween(100000, 999999999),
            'username' => fake()->userName(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => null,
            'language_code' => 'ru',
            'is_blocked' => false,
            'last_activity_at' => now(),
        ];
    }

    public function blocked(): static
    {
        return $this->state(fn () => ['is_blocked' => true]);
    }
}
