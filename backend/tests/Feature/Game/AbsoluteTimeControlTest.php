<?php

declare(strict_types=1);

namespace Tests\Feature\Game;

use App\Events\Game\MovePlayed;
use App\Jobs\CheckGameTimeouts;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
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

    public function test_clock_deduction_uses_previous_move_not_first_move(): void
    {
        // Regression test: Game::moves() relation has default orderBy('move_number') asc,
        // and latest() does not clear it — without reorder() the query returns move 1.
        // Without the fix, every move deducts time since the *first* move (cumulative bug).

        Carbon::setTestNow('2026-05-08 10:00:00');

        $game = Game::factory()->create([
            'black_player_id' => $this->alice->id,
            'white_player_id' => $this->bob->id,
            'mode' => 'realtime',
            'status' => 'playing',
            'current_turn' => 'black',
            'time_control_type' => 'absolute',
            'time_control_config' => ['main_time' => 600],
            'black_clock' => ['remaining_ms' => 600_000],
            'white_clock' => ['remaining_ms' => 600_000],
            'expires_at' => now()->addSeconds(600),
            'started_at' => now(),
        ]);

        // Move 1 (black) at +5s
        Carbon::setTestNow(now()->addSeconds(5));
        $this->actingAs($this->alice)->postJson("/api/games/{$game->id}/moves", [
            'x' => 0, 'y' => 0,
        ])->assertOk();

        // Move 2 (white) at +6s — bot took 1s
        Carbon::setTestNow(now()->addSeconds(1));
        $this->actingAs($this->bob)->postJson("/api/games/{$game->id}/moves", [
            'x' => 1, 'y' => 0,
        ])->assertOk();

        $game->refresh();
        $blackAfterMove1 = $game->black_clock['remaining_ms'];
        $whiteAfterMove2 = $game->white_clock['remaining_ms'];

        // Move 3 (black) at +9s — black took 3s since move 2
        Carbon::setTestNow(now()->addSeconds(3));
        $this->actingAs($this->alice)->postJson("/api/games/{$game->id}/moves", [
            'x' => 2, 'y' => 0,
        ])->assertOk();

        $game->refresh();
        $blackAfterMove3 = $game->black_clock['remaining_ms'];

        // Black's deduction on move 3 must be ~3s (since move 2), NOT ~9s (since game start).
        // Tolerate up to 500ms drift for test runtime.
        $deductionMove3 = $blackAfterMove1 - $blackAfterMove3;
        $this->assertGreaterThanOrEqual(2_500, $deductionMove3);
        $this->assertLessThanOrEqual(3_500, $deductionMove3);

        // Sanity: white's clock should not have changed since move 2.
        $this->assertEquals($whiteAfterMove2, $game->white_clock['remaining_ms']);

        Carbon::setTestNow(null);
    }

    public function test_event_turn_started_at_advances_with_each_move(): void
    {
        // Regression: same bug as above, on the broadcast event side —
        // turn_started_at must reflect the just-played move, not move 1.

        Event::fake();

        $game = Game::factory()->create([
            'black_player_id' => $this->alice->id,
            'white_player_id' => $this->bob->id,
            'mode' => 'realtime',
            'status' => 'playing',
            'current_turn' => 'black',
            'time_control_type' => 'absolute',
            'time_control_config' => ['main_time' => 600],
            'black_clock' => ['remaining_ms' => 600_000],
            'white_clock' => ['remaining_ms' => 600_000],
            'expires_at' => now()->addSeconds(600),
            'started_at' => now(),
        ]);

        Carbon::setTestNow(now()->addSeconds(2));
        $this->actingAs($this->alice)->postJson("/api/games/{$game->id}/moves", [
            'x' => 0, 'y' => 0,
        ])->assertOk();

        Carbon::setTestNow(now()->addSeconds(3));
        $this->actingAs($this->bob)->postJson("/api/games/{$game->id}/moves", [
            'x' => 1, 'y' => 0,
        ])->assertOk();

        $events = [];
        Event::assertDispatched(MovePlayed::class, function (MovePlayed $event) use (&$events): bool {
            $events[$event->moveNumber] = $event->turnStartedAt;

            return true;
        });

        $this->assertCount(2, $events);
        $this->assertNotNull($events[1]);
        $this->assertNotNull($events[2]);
        // Move 2's turn_started_at must be later than move 1's by ~3s.
        $diffSeconds = ($events[2] - $events[1]) / 1000;
        $this->assertEqualsWithDelta(3, $diffSeconds, 1);

        Carbon::setTestNow(null);
    }

    public function test_resource_turn_started_at_falls_back_to_started_at_without_moves(): void
    {
        $startedAt = now()->subSeconds(5);

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
            'started_at' => $startedAt,
            'last_move_at' => null,
        ]);

        $response = $this->actingAs($this->alice)->getJson("/api/games/{$game->id}");

        $response->assertOk();
        $data = $response->json('data');

        $this->assertIsInt($data['server_time']);
        $this->assertEqualsWithDelta((int) now()->getPreciseTimestamp(3), $data['server_time'], 1000);

        $this->assertIsInt($data['turn_started_at']);
        // DB stores started_at at second precision, so allow up to 1s delta
        $this->assertEqualsWithDelta(
            (int) $startedAt->getPreciseTimestamp(3),
            $data['turn_started_at'],
            1000,
        );
    }

    public function test_resource_turn_started_at_uses_last_move_at_after_move(): void
    {
        Carbon::setTestNow(now()->startOfSecond());

        $game = Game::factory()->create([
            'black_player_id' => $this->alice->id,
            'white_player_id' => $this->bob->id,
            'mode' => 'realtime',
            'status' => 'playing',
            'current_turn' => 'black',
            'time_control_type' => 'absolute',
            'time_control_config' => ['main_time' => 600],
            'black_clock' => ['remaining_ms' => 600_000],
            'white_clock' => ['remaining_ms' => 600_000],
            'expires_at' => now()->addSeconds(600),
            'started_at' => now(),
        ]);

        Carbon::setTestNow(now()->addSeconds(3));

        $this->actingAs($this->alice)->postJson("/api/games/{$game->id}/moves", [
            'x' => 0, 'y' => 0,
        ])->assertOk();

        $game->refresh();

        $response = $this->actingAs($this->alice)->getJson("/api/games/{$game->id}");
        $data = $response->json('data');

        $this->assertEqualsWithDelta(
            (int) $game->last_move_at->getPreciseTimestamp(3),
            $data['turn_started_at'],
            1000,
        );

        Carbon::setTestNow(null);
    }

    public function test_resource_turn_started_at_is_null_for_finished_game(): void
    {
        $game = Game::factory()->create([
            'black_player_id' => $this->alice->id,
            'white_player_id' => $this->bob->id,
            'mode' => 'realtime',
            'status' => 'finished',
            'time_control_type' => 'absolute',
            'time_control_config' => ['main_time' => 600],
            'result' => 'B+R',
        ]);

        $response = $this->actingAs($this->alice)->getJson("/api/games/{$game->id}");

        $response->assertOk()->assertJsonPath('data.turn_started_at', null);
    }

    public function test_move_event_payload_includes_server_time_and_turn_started_at(): void
    {
        Event::fake();

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

        $this->actingAs($this->alice)->postJson("/api/games/{$game->id}/moves", [
            'x' => 0, 'y' => 0,
        ])->assertOk();

        Event::assertDispatched(MovePlayed::class, function (MovePlayed $event): bool {
            $payload = $event->broadcastWith();

            return is_int($payload['server_time'])
                && is_int($payload['turn_started_at'])
                && $event->turnStartedAt === $payload['turn_started_at'];
        });
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
