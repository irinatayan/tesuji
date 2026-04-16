<?php

declare(strict_types=1);

namespace Tests\Feature\Game;

use App\Game\Engines\EngineMove;
use App\Game\Engines\GoEngine;
use App\Game\Stone;
use App\Jobs\BotMoveJob;
use App\Models\Game;
use App\Models\User;
use App\Services\GameService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BotPassHandlingTest extends TestCase
{
    use RefreshDatabase;

    private User $human;

    private User $bot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->human = User::factory()->create();
        $this->bot = User::factory()->create([
            'name' => 'GnuGo',
            'email' => 'bot+gnugo@tesuji.local',
            'is_bot' => true,
            'password' => null,
        ]);
    }

    public function test_two_passes_transition_to_scoring(): void
    {
        // Bind a fake engine that always passes BEFORE the human move,
        // because with sync queue the full chain runs within the HTTP request:
        // human pass → MovePassed → TriggerBotMove → BotMoveJob (sync)
        $this->app->bind(GoEngine::class, fn () => new class implements GoEngine
        {
            public function suggestMove(\App\Game\Board $board, Stone $toPlay, array $history = []): EngineMove
            {
                return EngineMove::pass();
            }
        });

        $game = Game::factory()->create([
            'black_player_id' => $this->human->id,
            'white_player_id' => $this->bot->id,
            'board_size' => 9,
            'status' => 'playing',
            'current_turn' => 'black',
        ]);

        // Human (black) passes → bot (white) auto-passes via job → scoring
        $this->actingAs($this->human)->postJson("/api/games/{$game->id}/pass")
            ->assertOk();

        $game->refresh();
        $this->assertSame('scoring', $game->status);
    }

    public function test_bot_pass_after_human_pass_dispatches_no_further_job(): void
    {
        $game = Game::factory()->create([
            'black_player_id' => $this->bot->id,
            'white_player_id' => $this->human->id,
            'board_size' => 9,
            'status' => 'playing',
            'current_turn' => 'black',
        ]);

        // Bind a fake engine that always passes
        $this->app->bind(GoEngine::class, fn () => new class implements GoEngine
        {
            public function suggestMove(\App\Game\Board $board, Stone $toPlay, array $history = []): EngineMove
            {
                return EngineMove::pass();
            }
        });

        // Bot (black) passes via job
        (new BotMoveJob($game->id))->handle(
            app(GameService::class),
            app(GoEngine::class),
        );

        $game->refresh();
        $this->assertSame('playing', $game->status);
        $this->assertSame('white', $game->current_turn);

        // Human (white) passes
        $this->actingAs($this->human)->postJson("/api/games/{$game->id}/pass")
            ->assertOk()
            ->assertJsonPath('data.status', 'scoring');
    }
}
