<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Game\Board;
use App\Game\Persistence\BoardSerializer;
use App\Models\Game;
use App\Models\Move;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Move>
 */
class MoveFactory extends Factory
{
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'move_number' => 1,
            'color' => 'black',
            'type' => 'pass',
            'x' => null,
            'y' => null,
            'captures' => [],
            'position_hash' => str_repeat('0', 64),
            'board_state' => BoardSerializer::serialize(Board::empty(9)),
            'played_at' => now(),
        ];
    }

    public function play(int $x, int $y): static
    {
        return $this->state(fn () => [
            'type' => 'play',
            'x' => $x,
            'y' => $y,
        ]);
    }
}
