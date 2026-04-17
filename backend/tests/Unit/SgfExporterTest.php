<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Game;
use App\Models\Move;
use App\Models\User;
use App\Services\SgfExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SgfExporterTest extends TestCase
{
    use RefreshDatabase;

    private SgfExporter $exporter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->exporter = new SgfExporter;
    }

    public function test_exports_basic_game(): void
    {
        $black = User::factory()->create(['name' => 'Alice']);
        $white = User::factory()->create(['name' => 'Bob']);

        $game = Game::factory()->create([
            'black_player_id' => $black->id,
            'white_player_id' => $white->id,
            'board_size' => 9,
            'status' => 'finished',
            'result' => 'B+12.5',
            'started_at' => '2025-04-17',
        ]);

        Move::factory()->play(2, 3)->create([
            'game_id' => $game->id,
            'move_number' => 1,
            'color' => 'black',
        ]);

        Move::factory()->play(6, 6)->create([
            'game_id' => $game->id,
            'move_number' => 2,
            'color' => 'white',
        ]);

        $sgf = $this->exporter->export($game);

        $this->assertStringContainsString('GM[1]', $sgf);
        $this->assertStringContainsString('FF[4]', $sgf);
        $this->assertStringContainsString('SZ[9]', $sgf);
        $this->assertStringContainsString('KM[5.5]', $sgf);
        $this->assertStringContainsString('PB[Alice]', $sgf);
        $this->assertStringContainsString('PW[Bob]', $sgf);
        $this->assertStringContainsString('RE[B+12.5]', $sgf);
        $this->assertStringContainsString('DT[2025-04-17]', $sgf);
        $this->assertStringContainsString(';B[cd]', $sgf);
        $this->assertStringContainsString(';W[gg]', $sgf);
    }

    public function test_exports_pass_moves(): void
    {
        $black = User::factory()->create();
        $white = User::factory()->create();

        $game = Game::factory()->create([
            'black_player_id' => $black->id,
            'white_player_id' => $white->id,
            'board_size' => 9,
            'status' => 'finished',
            'result' => 'B+5.5',
        ]);

        Move::factory()->play(0, 0)->create([
            'game_id' => $game->id,
            'move_number' => 1,
            'color' => 'black',
        ]);

        Move::factory()->create([
            'game_id' => $game->id,
            'move_number' => 2,
            'color' => 'white',
            'type' => 'pass',
        ]);

        $sgf = $this->exporter->export($game);

        $this->assertStringContainsString(';B[aa]', $sgf);
        $this->assertStringContainsString(';W[]', $sgf);
    }

    public function test_exports_resign_stops_at_resign(): void
    {
        $black = User::factory()->create();
        $white = User::factory()->create();

        $game = Game::factory()->create([
            'black_player_id' => $black->id,
            'white_player_id' => $white->id,
            'board_size' => 9,
            'status' => 'finished',
            'result' => 'W+R',
        ]);

        Move::factory()->play(0, 0)->create([
            'game_id' => $game->id,
            'move_number' => 1,
            'color' => 'black',
        ]);

        Move::factory()->play(1, 1)->create([
            'game_id' => $game->id,
            'move_number' => 2,
            'color' => 'white',
        ]);

        Move::factory()->create([
            'game_id' => $game->id,
            'move_number' => 3,
            'color' => 'black',
            'type' => 'resign',
        ]);

        $sgf = $this->exporter->export($game);

        $this->assertStringContainsString('RE[W+R]', $sgf);
        $this->assertStringContainsString(';B[aa]', $sgf);
        $this->assertStringContainsString(';W[bb]', $sgf);
        $this->assertStringNotContainsString('resign', strtolower($sgf));
    }

    public function test_escapes_special_characters_in_names(): void
    {
        $black = User::factory()->create(['name' => 'Ali]ce']);
        $white = User::factory()->create(['name' => 'Bob\\Jr']);

        $game = Game::factory()->create([
            'black_player_id' => $black->id,
            'white_player_id' => $white->id,
            'board_size' => 9,
            'status' => 'finished',
            'result' => 'B+0.5',
        ]);

        $sgf = $this->exporter->export($game);

        $this->assertStringContainsString('PB[Ali\\]ce]', $sgf);
        $this->assertStringContainsString('PW[Bob\\\\Jr]', $sgf);
    }
}
