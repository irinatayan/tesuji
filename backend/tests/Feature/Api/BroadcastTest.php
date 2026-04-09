<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Events\Game\DeadStonesMarked;
use App\Events\Game\GameFinished;
use App\Events\Game\MovePassed;
use App\Events\Game\MovePlayed;
use App\Events\Game\PlayerResigned;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class BroadcastTest extends TestCase
{
    use RefreshDatabase;

    private User $alice;

    private User $bob;

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();
        $this->alice = User::factory()->create();
        $this->bob = User::factory()->create();
        $this->game = Game::factory()->create([
            'black_player_id' => $this->alice->id,
            'white_player_id' => $this->bob->id,
            'board_size' => 9,
        ]);
    }

    public function test_move_dispatches_move_played_event(): void
    {
        Event::fake();

        $this->actingAs($this->alice)->postJson("/api/games/{$this->game->id}/moves", [
            'x' => 2,
            'y' => 3,
        ])->assertOk();

        Event::assertDispatched(MovePlayed::class, function (MovePlayed $event): bool {
            return $event->gameId === $this->game->id
                && $event->x === 2
                && $event->y === 3
                && $event->color === 'black'
                && $event->moveNumber === 1;
        });
    }

    public function test_pass_dispatches_move_passed_event(): void
    {
        Event::fake();

        $this->actingAs($this->alice)->postJson("/api/games/{$this->game->id}/pass")
            ->assertOk();

        Event::assertDispatched(MovePassed::class, function (MovePassed $event): bool {
            return $event->gameId === $this->game->id
                && $event->color === 'black'
                && $event->moveNumber === 1;
        });
    }

    public function test_resign_dispatches_player_resigned_and_game_finished(): void
    {
        Event::fake();

        $this->actingAs($this->alice)->postJson("/api/games/{$this->game->id}/resign")
            ->assertOk();

        Event::assertDispatched(PlayerResigned::class, function (PlayerResigned $event): bool {
            return $event->gameId === $this->game->id && $event->color === 'black';
        });

        Event::assertDispatched(GameFinished::class, function (GameFinished $event): bool {
            return $event->gameId === $this->game->id && $event->result === 'W+R';
        });
    }

    public function test_two_passes_dispatch_move_passed_twice_but_not_game_finished(): void
    {
        Event::fake();

        $this->actingAs($this->alice)->postJson("/api/games/{$this->game->id}/pass")->assertOk();
        $this->actingAs($this->bob)->postJson("/api/games/{$this->game->id}/pass")->assertOk();

        Event::assertDispatchedTimes(MovePassed::class, 2);
        Event::assertNotDispatched(GameFinished::class);
    }

    public function test_mark_dead_dispatches_dead_stones_marked(): void
    {
        Event::fake();

        // Put game into scoring phase via two passes
        $this->actingAs($this->alice)->postJson("/api/games/{$this->game->id}/pass");
        $this->actingAs($this->bob)->postJson("/api/games/{$this->game->id}/pass");

        Event::clearResolvedInstances();
        Event::fake();

        $this->actingAs($this->alice)->postJson("/api/games/{$this->game->id}/dead-stones", [
            'stones' => [['x' => 1, 'y' => 1]],
        ])->assertOk();

        Event::assertDispatched(DeadStonesMarked::class, function (DeadStonesMarked $event): bool {
            return $event->gameId === $this->game->id
                && $event->by === 'black'
                && $event->stones === [['x' => 1, 'y' => 1]];
        });
    }

    public function test_confirm_dead_dispatches_game_finished(): void
    {
        // Two passes → scoring
        $this->actingAs($this->alice)->postJson("/api/games/{$this->game->id}/pass");
        $this->actingAs($this->bob)->postJson("/api/games/{$this->game->id}/pass");

        // Alice proposes dead stones (none)
        $this->actingAs($this->alice)->postJson("/api/games/{$this->game->id}/dead-stones", [
            'stones' => [],
        ]);

        Event::fake();

        // Bob confirms
        $this->actingAs($this->bob)->postJson("/api/games/{$this->game->id}/dead-stones/confirm")
            ->assertOk();

        Event::assertDispatched(GameFinished::class, function (GameFinished $event): bool {
            return $event->gameId === $this->game->id;
        });
    }

    public function test_channel_authorizes_game_participant(): void
    {
        $authorized = $this->callChannelCallback($this->alice, $this->game->id);

        $this->assertTrue($authorized);
    }

    public function test_channel_denies_non_participant(): void
    {
        $outsider = User::factory()->create();

        $authorized = $this->callChannelCallback($outsider, $this->game->id);

        $this->assertFalse($authorized);
    }

    private function callChannelCallback(User $user, int $gameId): bool
    {
        $game = Game::find($gameId);

        return $game !== null && (
            $user->id === $game->black_player_id ||
            $user->id === $game->white_player_id
        );
    }
}
