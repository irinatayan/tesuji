<?php

declare(strict_types=1);

namespace Tests\Feature\Game;

use App\Models\Game;
use App\Models\Move;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_game_has_black_and_white_players(): void
    {
        $game = Game::factory()->create();

        $this->assertNotNull($game->blackPlayer);
        $this->assertNotNull($game->whitePlayer);
    }

    public function test_jsonb_fields_cast_to_arrays(): void
    {
        $game = Game::factory()->create([
            'time_control_config' => ['seconds' => 300],
            'black_clock' => ['remaining_seconds' => 300],
        ]);

        $game->refresh();

        $this->assertIsArray($game->time_control_config);
        $this->assertSame(300, $game->time_control_config['seconds']);
        $this->assertIsArray($game->black_clock);
    }

    public function test_game_moves_ordered_by_move_number(): void
    {
        $game = Game::factory()->create();

        Move::factory()->create(['game_id' => $game->id, 'move_number' => 2, 'color' => 'white']);
        Move::factory()->create(['game_id' => $game->id, 'move_number' => 1, 'color' => 'black']);

        $numbers = $game->moves->pluck('move_number')->all();

        $this->assertSame([1, 2], $numbers);
    }

    public function test_duplicate_move_number_throws_unique_violation(): void
    {
        $this->expectException(UniqueConstraintViolationException::class);

        $game = Game::factory()->create();

        Move::factory()->create(['game_id' => $game->id, 'move_number' => 1]);
        Move::factory()->create(['game_id' => $game->id, 'move_number' => 1]);
    }
}
