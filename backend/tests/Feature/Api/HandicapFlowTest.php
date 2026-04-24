<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Game;
use App\Models\GameInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HandicapFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $sender;

    private User $receiver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sender = User::factory()->create();
        $this->receiver = User::factory()->create();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'to_user_id' => $this->receiver->id,
            'board_size' => 9,
            'mode' => 'correspondence',
            'time_control_type' => 'correspondence',
            'time_control_config' => ['days_per_move' => 3],
            'proposed_color' => 'white',
        ], $overrides);
    }

    public function test_invitation_can_be_created_with_handicap(): void
    {
        $this->actingAs($this->sender)
            ->postJson('/api/invitations', $this->payload(['handicap' => 4]))
            ->assertStatus(201);

        $invitation = GameInvitation::first();
        $this->assertSame(4, (int) $invitation->handicap);
        $this->assertSame('fixed', $invitation->handicap_placement);
    }

    public function test_handicap_1_is_rejected(): void
    {
        $this->actingAs($this->sender)
            ->postJson('/api/invitations', $this->payload(['handicap' => 1]))
            ->assertStatus(422);
    }

    public function test_handicap_out_of_range_for_board_is_rejected(): void
    {
        $this->actingAs($this->sender)
            ->postJson('/api/invitations', $this->payload([
                'board_size' => 9,
                'handicap' => 6,
            ]))
            ->assertStatus(422);
    }

    public function test_accepting_handicap_invitation_creates_game_with_stones_and_white_turn(): void
    {
        $this->actingAs($this->sender)
            ->postJson('/api/invitations', $this->payload(['handicap' => 4]));

        $invitation = GameInvitation::first();

        $response = $this->actingAs($this->receiver)
            ->postJson("/api/invitations/{$invitation->id}/accept")
            ->assertStatus(200);

        $gameId = $response->json('game_id');
        $game = Game::findOrFail($gameId);

        $this->assertSame(4, (int) $game->handicap);
        $this->assertCount(4, $game->handicap_stones);
        $this->assertSame('white', $game->current_turn);
        $this->assertEqualsWithDelta(0.5, (float) $game->komi, 0.001);
    }

    public function test_handicap_game_response_exposes_stones_on_board(): void
    {
        $this->actingAs($this->sender)
            ->postJson('/api/invitations', $this->payload(['handicap' => 2]));

        $invitation = GameInvitation::first();
        $gameId = $this->actingAs($this->receiver)
            ->postJson("/api/invitations/{$invitation->id}/accept")
            ->json('game_id');

        $body = $this->actingAs($this->sender)
            ->getJson("/api/games/{$gameId}")
            ->assertStatus(200)
            ->json('data');

        $this->assertSame(2, $body['handicap']);
        $this->assertCount(2, $body['handicap_stones']);
        $this->assertSame('white', $body['current_turn']);
        $this->assertEqualsWithDelta(0.5, $body['komi'], 0.001);

        // Board should already contain two black stones at the handicap positions.
        $blackCount = 0;
        foreach ($body['board'] as $row) {
            foreach ($row as $cell) {
                if ($cell === 'black') {
                    $blackCount++;
                }
            }
        }
        $this->assertSame(2, $blackCount);
    }

    public function test_handicap_game_rejects_black_opening_move(): void
    {
        $this->actingAs($this->sender)
            ->postJson('/api/invitations', $this->payload(['handicap' => 2]));

        $invitation = GameInvitation::first();
        $gameId = $this->actingAs($this->receiver)
            ->postJson("/api/invitations/{$invitation->id}/accept")
            ->json('game_id');

        // Black tries to move first — illegal (it's White's turn).
        // `sender` proposed white color, so receiver is black.
        $this->actingAs($this->receiver)
            ->postJson("/api/games/{$gameId}/moves", ['x' => 3, 'y' => 3])
            ->assertStatus(422);
    }

    public function test_handicap_game_accepts_white_opening_move(): void
    {
        $this->actingAs($this->sender)
            ->postJson('/api/invitations', $this->payload(['handicap' => 2]));

        $invitation = GameInvitation::first();
        $gameId = $this->actingAs($this->receiver)
            ->postJson("/api/invitations/{$invitation->id}/accept")
            ->json('game_id');

        // `sender` is white (proposed white).
        $this->actingAs($this->sender)
            ->postJson("/api/games/{$gameId}/moves", ['x' => 4, 'y' => 4])
            ->assertStatus(200);
    }
}
