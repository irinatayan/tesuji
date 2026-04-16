<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Jobs\BotMoveJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class VsBotGameTest extends TestCase
{
    use RefreshDatabase;

    private User $player;

    private User $bot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->player = User::factory()->create();
        $this->bot = User::factory()->create([
            'name' => 'GnuGo',
            'email' => 'bot+gnugo@tesuji.local',
            'is_bot' => true,
            'password' => null,
        ]);
    }

    public function test_create_vs_bot_as_black(): void
    {
        Bus::fake(BotMoveJob::class);

        $response = $this->actingAs($this->player)->postJson('/api/games/vs-bot', [
            'board_size' => 9,
            'color' => 'black',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'playing')
            ->assertJsonPath('data.black_player.id', $this->player->id)
            ->assertJsonPath('data.white_player.id', $this->bot->id);

        Bus::assertNotDispatched(BotMoveJob::class);
    }

    public function test_create_vs_bot_as_white_dispatches_bot_move(): void
    {
        Bus::fake(BotMoveJob::class);

        $response = $this->actingAs($this->player)->postJson('/api/games/vs-bot', [
            'board_size' => 9,
            'color' => 'white',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.black_player.id', $this->bot->id)
            ->assertJsonPath('data.white_player.id', $this->player->id);

        Bus::assertDispatched(BotMoveJob::class);
    }

    public function test_create_vs_bot_requires_auth(): void
    {
        $this->postJson('/api/games/vs-bot', [
            'board_size' => 9,
            'color' => 'black',
        ])->assertUnauthorized();
    }

    public function test_create_vs_bot_validates_board_size(): void
    {
        $this->actingAs($this->player)->postJson('/api/games/vs-bot', [
            'board_size' => 7,
            'color' => 'black',
        ])->assertUnprocessable();
    }

    public function test_create_vs_bot_validates_color(): void
    {
        $this->actingAs($this->player)->postJson('/api/games/vs-bot', [
            'board_size' => 9,
            'color' => 'green',
        ])->assertUnprocessable();
    }

    public function test_create_vs_bot_defaults_to_realtime(): void
    {
        Bus::fake(BotMoveJob::class);

        $response = $this->actingAs($this->player)->postJson('/api/games/vs-bot', [
            'board_size' => 13,
            'color' => 'black',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.mode', 'realtime');
    }
}
