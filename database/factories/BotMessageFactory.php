<?php

namespace Database\Factories;

use App\Enums\MessageDirection;
use App\Models\BotMessage;
use App\Models\TelegramUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BotMessage>
 */
class BotMessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'telegram_user_id' => TelegramUser::factory(),
            'direction' => MessageDirection::In,
            'text' => fake()->sentence(),
        ];
    }
}
