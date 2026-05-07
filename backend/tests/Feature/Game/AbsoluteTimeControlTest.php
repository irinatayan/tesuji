<?php

declare(strict_types=1);

namespace Tests\Feature\Game;

use App\Jobs\CheckGameTimeouts;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AbsoluteTimeControlTest extends TestCase
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

    public function test_create_absolute_game_initializes_clocks(): void
    {
        $response = $this->actingAs($this->alice)->postJson('/api/games', [
            'opponent_id' => $this->bob->id,
            'board_size' => 9,
            'mode' => 'realtime',
            'time_control_type' => 'absolute',
            'time_control_config' => ['main_time' => 300],
            'color' => 'black',
        ]);

        $response->assertStatus(201);
        $data = $response->json('data');

        $this->assertEquals(['remaining_ms' => 300_000], $data['black_clock']);
        $this->assertEquals(['remaining_ms' => 300_000], $data['white_clock']);
        $this->assertNotNull($data['expires_at']);

        $game = Game::find($data['id']);
        $this->assertEqualsWithDelta(
            now()->addSeconds(300)->timestamp,
            Carbon::parse($game->expires_at)->timestamp,
            2,
        );
    }

    public function test_move_deducts_time_from_current_players_clock(): void
    {
        Carbon::setTestNow(now());

        $game = Game::factory()->create([
            'black_player_id' => $this->alice->id,
            'white_player_id' => $this->bob->id,
            'mode' => 'realtime',
            'status' => 'playing',
            'current_turn' => 'black',
            'time_control_type' => 'absolute',
            'time_control_config' => ['main_time' => 300],
            'black_clock' => ['remaining_ms' => 300_000],
            'white_clock' => ['remaining_ms' => 300_000],
            'expires_at' => now()->addSeconds(300),
            'started_at' => now()->subSeconds(10),
        ]);

        Carbon::setTestNow(now()->addSeconds(10));

        $this->actingAs($this->alice)->postJson("/api/games/{$game->id}/moves", [
            'x' => 0, 'y' => 0,
        ])->assertStatus(200);

        $game->refresh();

        $this->assertLessThan(300_000, $game->black_clock['remaining_ms']);
        $this->assertEquals(300_000, $game->white_clock['remaining_ms']);
        $this->assertNotNull($game->expires_at);

        Carbon::setTestNow(null);
    }

    public function test_expires_at_set_to_opponents_remaining_after_move(): void
    {
        Carbon::setTestNow(now());

        $game = Game::factory()->create([
            'black_player_id' => $this->alice->id,
            'white_player_id' => $this->bob->id,
            'mode' => 'realtime',
            'status' => 'playing',
            'current_turn' => 'black',
            'time_control_type' => 'absolute',
            'time_control_config' => ['main_time' => 300],
            'black_clock' => ['remaining_ms' => 300_000],
            'white_clock' => ['remaining_ms' => 200_000],
            'expires_at' => now()->addSeconds(300),
            'started_at' => now(),
        ]);

        $this->actingAs($this->alice)->postJson("/api/games/{$game->id}/moves", [
            'x' => 0, 'y' => 0,
        ])->assertStatus(200);

        $game->refresh();

        $whiteRemaining = $game->white_clock['remaining_ms'];
        $this->assertEqualsWithDelta(
            now()->addMilliseconds($whiteRemaining)->timestamp,
            Carbon::parse($game->expires_at)->timestamp,
            2,
        );

        Carbon::setTestNow(null);
    }

    public function test_game_resource_exposes_clock_fields(): void
    {
        $game = Game::factory()->create([
            'black_player_id' => $this->alice->id,
            'white_player_id' => $this->bob->id,
            'mode' => 'realtime',
            'status' => 'playing',
            'time_control_type' => 'absolute',
            'time_control_config' => ['main_time' => 600],
            'black_clock' => ['remaining_ms' => 600_000],
            'white_clock' => ['remaining_ms' => 600_000],
            'expires_at' => now()->addSeconds(600),
        ]);

        $response = $this->actingAs($this->alice)->getJson("/api/games/{$game->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.time_control_type', 'absolute');
        $response->assertJsonPath('data.time_control_config.main_time', 600);
        $response->assertJsonPath('data.black_clock.remaining_ms', 600_000);
        $response->assertJsonPath('data.white_clock.remaining_ms', 600_000);
        $response->assertJsonStructure(['data' => ['expires_at']]);
    }

    public function test_clocks_cleared_when_game_enters_scoring(): void
    {
        $game = Game::factory()->create([
            'black_player_id' => $this->alice->id,
            'white_player_id' => $this->bob->id,
            'mode' => 'realtime',
            'status' => 'playing',
            'current_turn' => 'black',
            'time_control_type' => 'absolute',
            'time_control_config' => ['main_time' => 300],
            'black_clock' => ['remaining_ms' => 300_000],
            'white_clock' => ['remaining_ms' => 300_000],
            'expires_at' => now()->addSeconds(300),
            'started_at' => now(),
        ]);

        $this->actingAs($this->alice)->postJson("/api/games/{$game->id}/pass")->assertStatus(200);
        $this->actingAs($this->bob)->postJson("/api/games/{$game->id}/pass")->assertStatus(200);

        $game->refresh();
        $this->assertEquals('scoring', $game->status);
        $this->assertNull($game->expires_at);
    }

    public function test_move_attempt_on_expired_clock_ends_game(): void
    {
        $game = Game::factory()->create([
            'black_player_id' => $this->alice->id,
            'white_player_id' => $this->bob->id,
            'mode' => 'realtime',
            'status' => 'playing',
            'current_turn' => 'black',
            'time_control_type' => 'absolute',
            'time_control_config' => ['main_time' => 300],
            'black_clock' => ['remaining_ms' => 0],
            'white_clock' => ['remaining_ms' => 300_000],
            'expires_at' => now()->subSecond(),
        ]);

        $response = $this->actingAs($this->alice)->postJson("/api/games/{$game->id}/moves", [
            'x' => 0, 'y' => 0,
        ]);

        $response->assertOk()->assertJsonPath('data.status', 'finished');

        $game->refresh();
        $this->assertEquals('finished', $game->status);
        $this->assertEquals('W+T', $game->result);
    }

    public function test_absolute_game_times_out_via_job(): void
    {
        $game = Game::factory()->create([
            'black_player_id' => $this->alice->id,
            'white_player_id' => $this->bob->id,
            'mode' => 'realtime',
            'status' => 'playing',
            'current_turn' => 'black',
            'time_control_type' => 'absolute',
            'time_control_config' => ['main_time' => 300],
            'black_clock' => ['remaining_ms' => 0],
            'white_clock' => ['remaining_ms' => 300_000],
            'expires_at' => now()->subMinute(),
        ]);

        CheckGameTimeouts::dispatch();

        $game->refresh();
        $this->assertEquals('finished', $game->status);
        $this->assertEquals('W+T', $game->result);
    }
}
