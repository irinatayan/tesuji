<?php

declare(strict_types=1);

namespace Tests\Feature\Game;

use App\Game\Engines\EngineMove;
use App\Game\Engines\GoEngine;
use App\Game\Stone;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BotConfirmDeadTest extends TestCase
{
    use RefreshDatabase;

    private User $human;

    private User $bot;

    protected function setUp(): void
    {
        parent::setUp();

        // Bind fake engine before any game actions
        $this->app->bind(GoEngine::class, fn () => new class implements GoEngine
        {
            public function suggestMove(\App\Game\Board $board, Stone $toPlay, array $history = []): EngineMove
            {
                return EngineMove::pass();
            }
        });

        $this->human = User::factory()->create();
        $this->bot = User::factory()->create([
            'name' => 'GnuGo',
            'email' => 'bot+gnugo@tesuji.local',
            'is_bot' => true,
            'password' => null,
        ]);
    }

    public function test_bot_auto_confirms_dead_stones(): void
    {
        $game = Game::factory()->create([
            'black_player_id' => $this->human->id,
            'white_player_id' => $this->bot->id,
            'board_size' => 9,
            'status' => 'playing',
            'current_turn' => 'black',
        ]);

        // Two passes → scoring (human pass, bot auto-passes via event chain)
        $this->actingAs($this->human)->postJson("/api/games/{$game->id}/pass");

        $game->refresh();
        $this->assertSame('scoring', $game->status);

        // Human marks dead stones (empty list = no dead stones)
        // This triggers DeadStonesMarked → TriggerBotConfirmDead → BotConfirmDeadStonesJob
        $this->actingAs($this->human)->postJson("/api/games/{$game->id}/dead-stones", [
            'stones' => [],
        ])->assertOk();

        // With sync queue, the bot auto-confirmed and the game is finished
        $game->refresh();
        $this->assertSame('finished', $game->status);
        $this->assertNotNull($game->result);
    }
}
