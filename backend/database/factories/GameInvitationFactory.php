<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\GameInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameInvitation>
 */
class GameInvitationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'from_user_id' => User::factory(),
            'to_user_id' => User::factory(),
            'mode' => 'realtime',
            'board_size' => 9,
            'ruleset' => 'chinese',
            'time_control_type' => 'absolute',
            'time_control_config' => ['seconds' => 600],
            'proposed_color' => 'random',
            'status' => 'pending',
            'game_id' => null,
            'expires_at' => now()->addDays(3),
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn () => [
            'status' => 'accepted',
        ]);
    }

    public function declined(): static
    {
        return $this->state(fn () => [
            'status' => 'declined',
        ]);
    }
}
