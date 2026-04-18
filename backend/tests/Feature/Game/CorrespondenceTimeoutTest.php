<?php

declare(strict_types=1);

namespace Tests\Feature\Game;

use App\Jobs\CheckGameTimeouts;
use App\Models\Game;
use App\Models\User;
use App\Notifications\GameTimedOutNotification;
use App\Notifications\OpponentMovedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CorrespondenceTimeoutTest extends TestCase
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

    public function test_create_correspondence_game_sets_expires_at(): void
    {
        $response = $this->actingAs($this->alice)->postJson('/api/games', [
            'opponent_id' => $this->bob->id,
            'board_size' => 9,
            'mode' => 'correspondence',
            'time_control_type' => 'correspondence',
            'time_control_config' => ['days_per_move' => 3],
            'color' => 'black',
        ]);

        $response->assertStatus(201);

        $game = Game::find($response->json('data.id'));
        $this->assertNotNull($game->expires_at);
        $this->assertTrue($game->expires_at->isFuture());
    }

    public function test_move_in_correspondence_game_refreshes_expires_at(): void
    {
        $game = Game::factory()->correspondence()->create([
            'black_player_id' => $this->alice->id,
            'white_player_id' => $this->bob->id,
            'expires_at' => now()->addDay(),
        ]);

        $before = $game->expires_at;

        $this->travel(1)->hours();

        $this->actingAs($this->alice)->postJson("/api/games/{$game->id}/moves", [
            'x' => 3, 'y' => 3,
        ])->assertOk();

        $this->assertTrue($game->fresh()->expires_at->isAfter($before));
    }

    public function test_two_passes_clear_expires_at(): void
    {
        $game = Game::factory()->correspondence()->create([
            'black_player_id' => $this->alice->id,
            'white_player_id' => $this->bob->id,
            'expires_at' => now()->addDays(3),
        ]);

        $this->actingAs($this->alice)->postJson("/api/games/{$game->id}/pass");
        $this->actingAs($this->bob)->postJson("/api/games/{$game->id}/pass")->assertOk();

        $this->assertNull($game->fresh()->expires_at);
    }

    public function test_timeout_finishes_game_with_correct_result(): void
    {
        $game = Game::factory()->correspondence()->create([
            'black_player_id' => $this->alice->id,
            'white_player_id' => $this->bob->id,
            'current_turn' => 'black',
            'expires_at' => now()->subMinute(),
        ]);

        (new CheckGameTimeouts)->handle();

        $game->refresh();
        $this->assertSame('finished', $game->status);
        $this->assertSame('W+T', $game->result);
        $this->assertNull($game->expires_at);
    }

    public function test_timeout_gives_victory_to_correct_player(): void
    {
        $game = Game::factory()->correspondence()->create([
            'black_player_id' => $this->alice->id,
            'white_player_id' => $this->bob->id,
            'current_turn' => 'white',
            'expires_at' => now()->subMinute(),
        ]);

        (new CheckGameTimeouts)->handle();

        $this->assertSame('B+T', $game->fresh()->result);
    }

    public function test_timeout_does_not_affect_finished_games(): void
    {
        $game = Game::factory()->finished()->create([
            'expires_at' => now()->subMinute(),
        ]);

        (new CheckGameTimeouts)->handle();

        $this->assertSame('finished', $game->fresh()->status);
        $this->assertSame('B+5.5', $game->fresh()->result);
    }

    public function test_timeout_sends_notification_to_both_players(): void
    {
        Notification::fake();

        Game::factory()->correspondence()->create([
            'black_player_id' => $this->alice->id,
            'white_player_id' => $this->bob->id,
            'current_turn' => 'black',
            'expires_at' => now()->subMinute(),
        ]);

        (new CheckGameTimeouts)->handle();

        Notification::assertSentTo($this->alice, GameTimedOutNotification::class);
        Notification::assertSentTo($this->bob, GameTimedOutNotification::class);
    }

    public function test_move_sends_your_turn_notification_in_correspondence(): void
    {
        Notification::fake();

        $game = Game::factory()->correspondence()->create([
            'black_player_id' => $this->alice->id,
            'white_player_id' => $this->bob->id,
            'expires_at' => now()->addDays(3),
        ]);

        $this->actingAs($this->alice)->postJson("/api/games/{$game->id}/moves", [
            'x' => 3, 'y' => 3,
        ])->assertOk();

        Notification::assertSentTo($this->bob, OpponentMovedNotification::class);
    }

    public function test_move_does_not_send_notification_in_realtime_game(): void
    {
        Notification::fake();

        $game = Game::factory()->create([
            'black_player_id' => $this->alice->id,
            'white_player_id' => $this->bob->id,
        ]);

        $this->actingAs($this->alice)->postJson("/api/games/{$game->id}/moves", [
            'x' => 3, 'y' => 3,
        ])->assertOk();

        Notification::assertNothingSent();
    }
}
