<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Game;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Game>
 */
class GameFactory extends Factory
{
    public function definition(): array
    {
        return [
            'black_player_id' => User::factory(),
            'white_player_id' => User::factory(),
            'mode' => 'realtime',
            'ruleset' => 'chinese',
            'board_size' => 9,
            'status' => 'playing',
            'current_turn' => 'black',
            'time_control_type' => 'absolute',
            'time_control_config' => ['main_time' => 600],
            'black_clock' => ['remaining_ms' => 600_000],
            'white_clock' => ['remaining_ms' => 600_000],
            'expires_at' => now()->addMinutes(10),
            'handicap' => 0,
            'handicap_stones' => [],
            'handicap_placement' => 'fixed',
            'komi' => 5.5,
            'started_at' => now(),
        ];
    }

    public function finished(): static
    {
        return $this->state(fn () => [
            'status' => 'finished',
            'current_turn' => null,
            'result' => 'B+5.5',
            'finished_at' => now(),
        ]);
    }

    public function scoring(): static
    {
        return $this->state(fn () => [
            'status' => 'scoring',
        ]);
    }

    public function correspondence(): static
    {
        return $this->state(fn () => [
            'mode' => 'correspondence',
            'time_control_type' => 'correspondence',
            'time_control_config' => ['days_per_move' => 3],
            'black_clock' => null,
            'white_clock' => null,
        ]);
    }
}
