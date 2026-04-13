<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Game;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class MessageTest extends TestCase
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
            'status' => 'playing',
        ]);
        RateLimiter::clear('message:'.$this->alice->id);
        RateLimiter::clear('message:'.$this->bob->id);
    }

    // --- Send ---

    public function test_player_can_send_message(): void
    {
        $response = $this->actingAs($this->alice)
            ->postJson("/api/games/{$this->game->id}/messages", ['text' => 'Hello!']);

        $response->assertStatus(201)
            ->assertJsonPath('data.user_id', $this->alice->id)
            ->assertJsonPath('data.user_name', $this->alice->name)
            ->assertJsonPath('data.text', 'Hello!');

        $this->assertDatabaseHas('messages', [
            'game_id' => $this->game->id,
            'user_id' => $this->alice->id,
            'text' => 'Hello!',
        ]);
    }

    public function test_non_participant_cannot_send_message(): void
    {
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->postJson("/api/games/{$this->game->id}/messages", ['text' => 'Hi'])
            ->assertForbidden();
    }

    public function test_message_text_max_500_chars(): void
    {
        $this->actingAs($this->alice)
            ->postJson("/api/games/{$this->game->id}/messages", ['text' => str_repeat('a', 501)])
            ->assertUnprocessable();
    }

    public function test_message_text_required(): void
    {
        $this->actingAs($this->alice)
            ->postJson("/api/games/{$this->game->id}/messages", ['text' => ''])
            ->assertUnprocessable();
    }

    public function test_rate_limit_blocks_third_message(): void
    {
        $this->actingAs($this->alice)
            ->postJson("/api/games/{$this->game->id}/messages", ['text' => 'msg1'])
            ->assertStatus(201);

        $this->actingAs($this->alice)
            ->postJson("/api/games/{$this->game->id}/messages", ['text' => 'msg2'])
            ->assertStatus(201);

        $this->actingAs($this->alice)
            ->postJson("/api/games/{$this->game->id}/messages", ['text' => 'msg3'])
            ->assertStatus(429);
    }

    // --- History ---

    public function test_player_can_fetch_message_history(): void
    {
        Message::factory()->count(3)->create([
            'game_id' => $this->game->id,
            'user_id' => $this->alice->id,
        ]);

        $response = $this->actingAs($this->bob)
            ->getJson("/api/games/{$this->game->id}/messages");

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_history_returns_max_50_messages(): void
    {
        Message::factory()->count(60)->create([
            'game_id' => $this->game->id,
            'user_id' => $this->alice->id,
        ]);

        $response = $this->actingAs($this->alice)
            ->getJson("/api/games/{$this->game->id}/messages");

        $response->assertOk()
            ->assertJsonCount(50, 'data');
    }

    public function test_history_after_param_returns_newer_messages(): void
    {
        $messages = Message::factory()->count(5)->create([
            'game_id' => $this->game->id,
            'user_id' => $this->alice->id,
        ]);
        $pivot = $messages->get(2);

        $response = $this->actingAs($this->alice)
            ->getJson("/api/games/{$this->game->id}/messages?after={$pivot->id}");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_non_participant_cannot_fetch_history(): void
    {
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->getJson("/api/games/{$this->game->id}/messages")
            ->assertForbidden();
    }
}
