<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
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

    // --- GET /api/users/{user} ---

    public function test_profile_returns_user_data_and_stats(): void
    {
        $this->actingAs($this->alice)
            ->getJson("/api/users/{$this->bob->id}")
            ->assertOk()
            ->assertJsonStructure([
                'id', 'name', 'created_at',
                'stats' => ['total', 'wins', 'losses', 'win_rate'],
            ])
            ->assertJsonPath('id', $this->bob->id)
            ->assertJsonPath('stats.total', 0)
            ->assertJsonPath('stats.wins', 0);
    }

    public function test_profile_requires_auth(): void
    {
        $this->getJson("/api/users/{$this->bob->id}")->assertUnauthorized();
    }

    public function test_profile_counts_finished_games(): void
    {
        Game::factory()->finished()->create([
            'black_player_id' => $this->alice->id,
            'white_player_id' => $this->bob->id,
            'result' => 'B+5.5',
        ]);

        Game::factory()->finished()->create([
            'black_player_id' => $this->bob->id,
            'white_player_id' => $this->alice->id,
            'result' => 'W+R',
        ]);

        $this->actingAs($this->bob)
            ->getJson("/api/users/{$this->alice->id}")
            ->assertOk()
            ->assertJsonPath('stats.total', 2)
            ->assertJsonPath('stats.wins', 2)
            ->assertJsonPath('stats.losses', 0)
            ->assertJsonPath('stats.win_rate', 100);
    }

    public function test_profile_calculates_losses_correctly(): void
    {
        Game::factory()->finished()->create([
            'black_player_id' => $this->alice->id,
            'white_player_id' => $this->bob->id,
            'result' => 'W+R',
        ]);

        $this->actingAs($this->bob)
            ->getJson("/api/users/{$this->alice->id}")
            ->assertOk()
            ->assertJsonPath('stats.wins', 0)
            ->assertJsonPath('stats.losses', 1);
    }

    public function test_profile_does_not_count_active_games(): void
    {
        Game::factory()->create([
            'black_player_id' => $this->alice->id,
            'white_player_id' => $this->bob->id,
            'status' => 'playing',
        ]);

        $this->actingAs($this->bob)
            ->getJson("/api/users/{$this->alice->id}")
            ->assertOk()
            ->assertJsonPath('stats.total', 0);
    }

    // --- GET /api/profile ---

    public function test_profile_alias_returns_own_profile(): void
    {
        $this->actingAs($this->alice)
            ->getJson('/api/profile')
            ->assertOk()
            ->assertJsonPath('id', $this->alice->id);
    }

    // --- GET /api/users/{user}/games ---

    public function test_game_history_returns_paginated_finished_games(): void
    {
        Game::factory()->finished()->create([
            'black_player_id' => $this->alice->id,
            'white_player_id' => $this->bob->id,
            'result' => 'B+5.5',
        ]);

        $this->actingAs($this->bob)
            ->getJson("/api/users/{$this->alice->id}/games")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'mode', 'board_size', 'result', 'finished_at']],
                'current_page', 'last_page', 'total',
            ])
            ->assertJsonPath('total', 1);
    }

    public function test_game_history_excludes_active_games(): void
    {
        Game::factory()->create([
            'black_player_id' => $this->alice->id,
            'white_player_id' => $this->bob->id,
            'status' => 'playing',
        ]);

        $this->actingAs($this->bob)
            ->getJson("/api/users/{$this->alice->id}/games")
            ->assertOk()
            ->assertJsonPath('total', 0);
    }

    public function test_game_history_requires_auth(): void
    {
        $this->getJson("/api/users/{$this->alice->id}/games")->assertUnauthorized();
    }
}
