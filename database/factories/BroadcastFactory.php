<?php

namespace Database\Factories;

use App\Enums\BroadcastStatus;
use App\Models\Broadcast;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Broadcast>
 */
class BroadcastFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'message' => fake()->paragraph(),
            'status' => BroadcastStatus::Draft,
            'recipients_count' => 0,
            'sent_count' => 0,
            'failed_count' => 0,
        ];
    }
}
