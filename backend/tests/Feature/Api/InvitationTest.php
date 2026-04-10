<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\GameInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvitationTest extends TestCase
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

    private function invitationPayload(array $overrides = []): array
    {
        return array_merge([
            'to_user_id' => $this->bob->id,
            'board_size' => 9,
            'mode' => 'realtime',
            'time_control_type' => 'absolute',
            'time_control_config' => ['seconds' => 600],
            'proposed_color' => 'black',
        ], $overrides);
    }

    // --- Create ---

    public function test_create_invitation_returns_201(): void
    {
        $this->actingAs($this->alice)
            ->postJson('/api/invitations', $this->invitationPayload())
            ->assertStatus(201)
            ->assertJsonPath('status', 'pending');
    }

    public function test_create_invitation_requires_auth(): void
    {
        $this->postJson('/api/invitations', $this->invitationPayload())->assertUnauthorized();
    }

    public function test_duplicate_pending_invitation_returns_422(): void
    {
        $this->actingAs($this->alice)->postJson('/api/invitations', $this->invitationPayload());

        $this->actingAs($this->alice)
            ->postJson('/api/invitations', $this->invitationPayload())
            ->assertStatus(422);
    }

    public function test_cannot_invite_yourself(): void
    {
        $this->actingAs($this->alice)
            ->postJson('/api/invitations', $this->invitationPayload(['to_user_id' => $this->alice->id]))
            ->assertStatus(422);
    }

    // --- Incoming / Outgoing ---

    public function test_incoming_returns_pending_invitations_for_recipient(): void
    {
        $this->actingAs($this->alice)->postJson('/api/invitations', $this->invitationPayload());

        $this->actingAs($this->bob)
            ->getJson('/api/invitations/incoming')
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_outgoing_returns_sent_invitations(): void
    {
        $this->actingAs($this->alice)->postJson('/api/invitations', $this->invitationPayload());

        $this->actingAs($this->alice)
            ->getJson('/api/invitations/outgoing')
            ->assertOk()
            ->assertJsonCount(1);
    }

    // --- Accept ---

    public function test_accept_creates_game_and_marks_invitation_accepted(): void
    {
        $this->actingAs($this->alice)->postJson('/api/invitations', $this->invitationPayload());
        $invitation = GameInvitation::first();

        $response = $this->actingAs($this->bob)
            ->postJson("/api/invitations/{$invitation->id}/accept")
            ->assertOk()
            ->assertJsonStructure(['game_id']);

        $this->assertSame('accepted', $invitation->fresh()->status);
        $this->assertNotNull($invitation->fresh()->game_id);
    }

    public function test_accept_removes_invitation_from_incoming(): void
    {
        $this->actingAs($this->alice)->postJson('/api/invitations', $this->invitationPayload());
        $invitation = GameInvitation::first();

        $this->actingAs($this->bob)->postJson("/api/invitations/{$invitation->id}/accept");

        $this->actingAs($this->bob)
            ->getJson('/api/invitations/incoming')
            ->assertOk()
            ->assertJsonCount(0);
    }

    public function test_accept_stranger_invitation_returns_403(): void
    {
        $this->actingAs($this->alice)->postJson('/api/invitations', $this->invitationPayload());
        $invitation = GameInvitation::first();

        $outsider = User::factory()->create();
        $this->actingAs($outsider)
            ->postJson("/api/invitations/{$invitation->id}/accept")
            ->assertStatus(403);
    }

    public function test_accept_already_accepted_invitation_returns_422(): void
    {
        $this->actingAs($this->alice)->postJson('/api/invitations', $this->invitationPayload());
        $invitation = GameInvitation::first();

        $this->actingAs($this->bob)->postJson("/api/invitations/{$invitation->id}/accept");
        $this->actingAs($this->bob)
            ->postJson("/api/invitations/{$invitation->id}/accept")
            ->assertStatus(422);
    }

    // --- Decline ---

    public function test_decline_marks_invitation_declined(): void
    {
        $this->actingAs($this->alice)->postJson('/api/invitations', $this->invitationPayload());
        $invitation = GameInvitation::first();

        $this->actingAs($this->bob)
            ->postJson("/api/invitations/{$invitation->id}/decline")
            ->assertOk();

        $this->assertSame('declined', $invitation->fresh()->status);
    }

    public function test_decline_stranger_invitation_returns_403(): void
    {
        $this->actingAs($this->alice)->postJson('/api/invitations', $this->invitationPayload());
        $invitation = GameInvitation::first();

        $outsider = User::factory()->create();
        $this->actingAs($outsider)
            ->postJson("/api/invitations/{$invitation->id}/decline")
            ->assertStatus(403);
    }
}
