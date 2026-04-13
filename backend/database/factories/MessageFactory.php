<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Game;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'user_id' => User::factory(),
            'text' => $this->faker->sentence(),
            'created_at' => now(),
        ];
    }
}
