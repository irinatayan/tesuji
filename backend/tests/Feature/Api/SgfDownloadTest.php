<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Game;
use App\Models\Move;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SgfDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_download_sgf_for_finished_game(): void
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

        Move::factory()->play(3, 4)->create([
            'game_id' => $game->id,
            'move_number' => 1,
            'color' => 'black',
        ]);

        $response = $this->actingAs($black)->getJson("/api/games/{$game->id}/sgf");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/x-go-sgf');
        $response->assertHeader('Content-Disposition', "attachment; filename=\"game-{$game->id}.sgf\"");
        $this->assertStringContainsString(';B[de]', $response->content());
    }

    public function test_returns_403_for_unfinished_game(): void
    {
        $user = User::factory()->create();

        $game = Game::factory()->create([
            'black_player_id' => $user->id,
            'white_player_id' => User::factory()->create()->id,
            'status' => 'playing',
        ]);

        $response = $this->actingAs($user)->getJson("/api/games/{$game->id}/sgf");

        $response->assertForbidden();
    }

    public function test_spectator_can_download_sgf(): void
    {
        $black = User::factory()->create();
        $white = User::factory()->create();
        $spectator = User::factory()->create();

        $game = Game::factory()->create([
            'black_player_id' => $black->id,
            'white_player_id' => $white->id,
            'board_size' => 9,
            'status' => 'finished',
            'result' => 'W+R',
        ]);

        $response = $this->actingAs($spectator)->getJson("/api/games/{$game->id}/sgf");

        $response->assertOk();
    }

    public function test_unauthenticated_cannot_download_sgf(): void
    {
        $game = Game::factory()->create(['status' => 'finished', 'result' => 'B+1']);

        $response = $this->getJson("/api/games/{$game->id}/sgf");

        $response->assertUnauthorized();
    }
}
