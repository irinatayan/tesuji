<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Events\Game\UnreadChanged;
use App\Models\Game;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class MessageReadTest extends TestCase
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

    public function test_mark_as_read_stores_last_read_message_id(): void
    {
        $message = Message::factory()->create([
            'game_id' => $this->game->id,
            'user_id' => $this->bob->id,
        ]);

        $this->actingAs($this->alice)
            ->postJson("/api/games/{$this->game->id}/messages/read", ['last_read_id' => $message->id])
            ->assertNoContent();

        $this->assertDatabaseHas('game_read_states', [
            'game_id' => $this->game->id,
            'user_id' => $this->alice->id,
            'last_read_message_id' => $message->id,
        ]);
    }

    public function test_non_participant_cannot_mark_as_read(): void
    {
        $outsider = User::factory()->create();
        $message = Message::factory()->create([
            'game_id' => $this->game->id,
            'user_id' => $this->alice->id,
        ]);

        $this->actingAs($outsider)
            ->postJson("/api/games/{$this->game->id}/messages/read", ['last_read_id' => $message->id])
            ->assertForbidden();
    }

    public function test_mark_as_read_rejects_message_from_other_game(): void
    {
        $otherGame = Game::factory()->create([
            'black_player_id' => $this->alice->id,
            'white_player_id' => $this->bob->id,
        ]);
        $message = Message::factory()->create([
            'game_id' => $otherGame->id,
            'user_id' => $this->bob->id,
        ]);

        $this->actingAs($this->alice)
            ->postJson("/api/games/{$this->game->id}/messages/read", ['last_read_id' => $message->id])
            ->assertUnprocessable();
    }

    public function test_mark_as_read_is_monotonic(): void
    {
        [$m1, $m2] = Message::factory()->count(2)->create([
            'game_id' => $this->game->id,
            'user_id' => $this->bob->id,
        ])->all();

        $this->actingAs($this->alice)
            ->postJson("/api/games/{$this->game->id}/messages/read", ['last_read_id' => $m2->id])
            ->assertNoContent();

        $this->actingAs($this->alice)
            ->postJson("/api/games/{$this->game->id}/messages/read", ['last_read_id' => $m1->id])
            ->assertNoContent();

        $state = DB::table('game_read_states')
            ->where('game_id', $this->game->id)
            ->where('user_id', $this->alice->id)
            ->first();
        $this->assertSame($m2->id, (int) $state->last_read_message_id);
    }

    public function test_games_response_includes_unread_count(): void
    {
        Message::factory()->count(3)->create([
            'game_id' => $this->game->id,
            'user_id' => $this->bob->id,
        ]);
        Message::factory()->create([
            'game_id' => $this->game->id,
            'user_id' => $this->alice->id,
        ]);

        $this->actingAs($this->alice)
            ->getJson('/api/games')
            ->assertOk()
            ->assertJsonPath('data.0.unread_count', 3);

        $this->actingAs($this->bob)
            ->getJson('/api/games')
            ->assertOk()
            ->assertJsonPath('data.0.unread_count', 1);
    }

    public function test_unread_count_drops_after_marking_as_read(): void
    {
        $messages = Message::factory()->count(3)->create([
            'game_id' => $this->game->id,
            'user_id' => $this->bob->id,
        ]);

        $this->actingAs($this->alice)
            ->postJson("/api/games/{$this->game->id}/messages/read", ['last_read_id' => $messages->last()->id])
            ->assertNoContent();

        $this->actingAs($this->alice)
            ->getJson("/api/games/{$this->game->id}")
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0);
    }

    public function test_sending_message_broadcasts_unread_changed_to_recipient(): void
    {
        Event::fake([UnreadChanged::class]);

        $this->actingAs($this->alice)
            ->postJson("/api/games/{$this->game->id}/messages", ['text' => 'hi'])
            ->assertStatus(201);

        Event::assertDispatched(
            UnreadChanged::class,
            fn (UnreadChanged $e) => $e->userId === $this->bob->id
                && $e->gameId === $this->game->id
                && $e->unreadCount === 1,
        );
    }
}
