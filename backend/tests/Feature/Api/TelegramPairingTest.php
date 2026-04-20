<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TelegramPairingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        config(['services.telegram.bot_username' => 'tesuji_bot']);
    }

    // --- POST /api/telegram/pair ---

    public function test_pair_returns_telegram_deep_link(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/telegram/pair')
            ->assertOk()
            ->assertJsonStructure(['url']);

        $this->assertStringContainsString('t.me/tesuji_bot?start=', $response->json('url'));
    }

    public function test_pair_stores_token_in_cache(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/telegram/pair')
            ->assertOk();

        $url = $response->json('url');
        preg_match('/start=(\w+)/', $url, $m);
        $token = $m[1];

        $this->assertEquals($this->user->id, Cache::get("telegram_pairing:{$token}"));
    }

    public function test_pair_requires_auth(): void
    {
        $this->postJson('/api/telegram/pair')->assertUnauthorized();
    }

    // --- DELETE /api/telegram/unlink ---

    public function test_unlink_clears_telegram_chat_id(): void
    {
        $this->user->update(['telegram_chat_id' => 123456]);

        $this->actingAs($this->user)
            ->deleteJson('/api/telegram/unlink')
            ->assertNoContent();

        $this->assertNull($this->user->fresh()->telegram_chat_id);
    }

    public function test_unlink_requires_auth(): void
    {
        $this->deleteJson('/api/telegram/unlink')->assertUnauthorized();
    }

    // --- POST /webhooks/telegram ---

    public function test_webhook_pairs_user_on_start_command(): void
    {
        Cache::put('telegram_pairing:abc123', $this->user->id, now()->addMinutes(10));

        $this->postJson('/api/webhooks/telegram', [
            'message' => [
                'text' => '/start abc123',
                'from' => ['id' => 999888],
            ],
        ])->assertOk();

        $this->assertEquals(999888, $this->user->fresh()->telegram_chat_id);
        $this->assertNull(Cache::get('telegram_pairing:abc123'));
    }

    public function test_webhook_rejects_expired_token(): void
    {
        $this->postJson('/api/webhooks/telegram', [
            'message' => [
                'text' => '/start invalid_token',
                'from' => ['id' => 999888],
            ],
        ])->assertOk();

        $this->assertNull($this->user->fresh()->telegram_chat_id);
    }

    public function test_webhook_ignores_non_start_messages(): void
    {
        $this->postJson('/api/webhooks/telegram', [
            'message' => [
                'text' => 'hello',
                'from' => ['id' => 999888],
            ],
        ])->assertOk();
    }
}
