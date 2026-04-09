<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameApiTest extends TestCase
{
    use RefreshDatabase;

    private User $alice;

    private User $bob;

    protected function setUp(): void
    {
        parent::setUp();
        $this->alice = User::factory()->create();
        $this->bob = User::factory()->create();
    }

    // --- Create ---

    public function test_create_game_assigns_players(): void
    {
        $response = $this->actingAs($this->alice)->postJson('/api/games', [
            'opponent_id' => $this->bob->id,
            'board_size' => 9,
            'mode' => 'realtime',
            'time_control_type' => 'absolute',
            'time_control_config' => ['seconds' => 600],
            'color' => 'black',
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['status' => 'playing'])
            ->assertJsonPath('data.black_player.id', $this->alice->id)
            ->assertJsonPath('data.white_player.id', $this->bob->id);
    }

    public function test_create_game_requires_auth(): void
    {
        $this->postJson('/api/games', [])->assertUnauthorized();
    }

    // --- Show ---

    public function test_show_returns_game_with_board(): void
    {
        $game = Game::factory()->create(['board_size' => 9]);

        $response = $this->actingAs($this->alice)->getJson("/api/games/{$game->id}");

        $response->assertOk()
            ->assertJsonStructure(['data' => ['id', 'board', 'status', 'current_turn']]);

        $board = $response->json('data.board');
        $this->assertCount(9, $board);
        $this->assertCount(9, $board[0]);
    }

    // --- Play move ---

    public function test_play_move_updates_board(): void
    {
        $game = Game::factory()->create([
            'black_player_id' => $this->alice->id,
            'white_player_id' => $this->bob->id,
            'board_size' => 9,
        ]);

        $response = $this->actingAs($this->alice)->postJson("/api/games/{$game->id}/moves", [
            'x' => 3, 'y' => 3,
        ]);

        $response->assertOk();
        $this->assertSame('black', $response->json('data.board.3.3'));
        $this->assertSame('white', $response->json('data.current_turn'));
    }

    public function test_play_move_on_occupied_cell_returns_422(): void
    {
        $game = Game::factory()->create([
            'black_player_id' => $this->alice->id,
            'white_player_id' => $this->bob->id,
            'board_size' => 9,
        ]);

        $this->actingAs($this->alice)->postJson("/api/games/{$game->id}/moves", ['x' => 3, 'y' => 3]);
        $this->actingAs($this->bob)->postJson("/api/games/{$game->id}/moves", ['x' => 3, 'y' => 3])
            ->assertStatus(422);
    }

    public function test_non_participant_cannot_play(): void
    {
        $game = Game::factory()->create([
            'black_player_id' => $this->alice->id,
            'white_player_id' => $this->bob->id,
        ]);

        $outsider = User::factory()->create();
        $this->actingAs($outsider)
            ->postJson("/api/games/{$game->id}/moves", ['x' => 0, 'y' => 0])
            ->assertStatus(403);
    }

    // --- Pass ---

    public function test_pass_switches_turn(): void
    {
        $game = Game::factory()->create([
            'black_player_id' => $this->alice->id,
            'white_player_id' => $this->bob->id,
        ]);

        $this->actingAs($this->alice)->postJson("/api/games/{$game->id}/pass")
            ->assertOk()
            ->assertJsonPath('data.current_turn', 'white');
    }

    public function test_two_passes_transition_to_scoring(): void
    {
        $game = Game::factory()->create([
            'black_player_id' => $this->alice->id,
            'white_player_id' => $this->bob->id,
        ]);

        $this->actingAs($this->alice)->postJson("/api/games/{$game->id}/pass");
        $this->actingAs($this->bob)->postJson("/api/games/{$game->id}/pass")
            ->assertOk()
            ->assertJsonPath('data.status', 'scoring');
    }

    // --- Resign ---

    public function test_resign_finishes_game(): void
    {
        $game = Game::factory()->create([
            'black_player_id' => $this->alice->id,
            'white_player_id' => $this->bob->id,
        ]);

        $this->actingAs($this->alice)->postJson("/api/games/{$game->id}/resign")
            ->assertOk()
            ->assertJsonPath('data.status', 'finished');
    }

    // --- Dead stones ---

    private function gameInScoringPhase(): Game
    {
        $game = Game::factory()->create([
            'black_player_id' => $this->alice->id,
            'white_player_id' => $this->bob->id,
        ]);

        $this->actingAs($this->alice)->postJson("/api/games/{$game->id}/pass");
        $this->actingAs($this->bob)->postJson("/api/games/{$game->id}/pass");

        return $game->fresh();
    }

    public function test_mark_dead_stores_proposal(): void
    {
        $game = $this->gameInScoringPhase();

        $this->actingAs($this->alice)->postJson("/api/games/{$game->id}/dead-stones", [
            'stones' => [],
        ])->assertOk()->assertJsonPath('data.status', 'scoring');
    }

    public function test_confirm_dead_finishes_game(): void
    {
        $game = $this->gameInScoringPhase();

        $this->actingAs($this->alice)->postJson("/api/games/{$game->id}/dead-stones", ['stones' => []]);
        $this->actingAs($this->bob)->postJson("/api/games/{$game->id}/dead-stones/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', 'finished');
    }

    public function test_dispute_dead_returns_to_playing(): void
    {
        $game = $this->gameInScoringPhase();

        $this->actingAs($this->alice)->postJson("/api/games/{$game->id}/dead-stones", ['stones' => []]);
        $this->actingAs($this->bob)->postJson("/api/games/{$game->id}/dead-stones/dispute")
            ->assertOk()
            ->assertJsonPath('data.status', 'playing');
    }
}
