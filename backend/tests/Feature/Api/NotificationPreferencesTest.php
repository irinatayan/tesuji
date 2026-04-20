<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use App\Notifications\Channels\TelegramChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationPreferencesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    // --- GET /api/profile ---

    public function test_profile_includes_telegram_connected_and_preferences(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/profile')
            ->assertOk()
            ->assertJsonStructure(['telegram_connected', 'notification_preferences'])
            ->assertJsonPath('telegram_connected', false);
    }

    public function test_profile_shows_telegram_connected_when_paired(): void
    {
        $this->user->update(['telegram_chat_id' => 123456]);

        $this->actingAs($this->user)
            ->getJson('/api/profile')
            ->assertOk()
            ->assertJsonPath('telegram_connected', true);
    }

    // --- PUT /api/profile/notifications ---

    public function test_can_save_notification_preferences(): void
    {
        $prefs = [
            'new_message' => ['telegram' => true, 'mail' => false],
            'opponent_moved' => ['telegram' => true, 'mail' => true],
            'invitation' => ['telegram' => false, 'mail' => true],
            'game_finished' => ['telegram' => false, 'mail' => false],
        ];

        $this->actingAs($this->user)
            ->putJson('/api/profile/notifications', $prefs)
            ->assertNoContent();

        $saved = $this->user->fresh()->notification_preferences;
        $this->assertTrue($saved['new_message']['telegram']);
        $this->assertFalse($saved['new_message']['mail']);
        $this->assertFalse($saved['invitation']['telegram']);
    }

    public function test_preferences_must_be_boolean(): void
    {
        $this->actingAs($this->user)
            ->putJson('/api/profile/notifications', [
                'new_message' => ['telegram' => 'yes', 'mail' => 0],
            ])
            ->assertUnprocessable();
    }

    public function test_preferences_requires_auth(): void
    {
        $this->putJson('/api/profile/notifications', [])->assertUnauthorized();
    }

    // --- User::channelsFor ---

    public function test_channels_for_returns_empty_when_no_preferences(): void
    {
        $channels = $this->user->channelsFor('opponent_moved');
        $this->assertEmpty($channels);
    }

    public function test_channels_for_returns_telegram_when_enabled_and_paired(): void
    {
        $this->user->update([
            'telegram_chat_id' => 123456,
            'notification_preferences' => ['opponent_moved' => ['telegram' => true, 'mail' => false]],
        ]);

        $channels = $this->user->fresh()->channelsFor('opponent_moved');
        $this->assertContains(TelegramChannel::class, $channels);
        $this->assertNotContains('mail', $channels);
    }

    public function test_channels_for_skips_telegram_when_not_paired(): void
    {
        $this->user->update([
            'notification_preferences' => ['opponent_moved' => ['telegram' => true, 'mail' => false]],
        ]);

        $channels = $this->user->fresh()->channelsFor('opponent_moved');
        $this->assertEmpty($channels);
    }

    public function test_channels_for_never_returns_mail(): void
    {
        $this->user->update([
            'notification_preferences' => ['game_finished' => ['telegram' => false, 'mail' => true]],
        ]);

        $channels = $this->user->fresh()->channelsFor('game_finished');
        $this->assertNotContains('mail', $channels);
    }
}
