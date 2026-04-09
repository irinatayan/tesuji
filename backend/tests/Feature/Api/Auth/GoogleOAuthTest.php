<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class GoogleOAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_callback_creates_new_user(): void
    {
        $this->mockGoogleUser('google-123', 'Alice', 'alice@example.com');

        $response = $this->get('/api/auth/google/callback');

        $response->assertRedirect();
        $this->assertStringContainsString('/auth/callback?token=', $response->headers->get('Location'));
        $this->assertDatabaseHas('users', [
            'email' => 'alice@example.com',
            'provider' => 'google',
            'provider_id' => 'google-123',
        ]);
    }

    public function test_google_callback_returns_token_for_existing_google_user(): void
    {
        User::factory()->create([
            'email' => 'alice@example.com',
            'provider' => 'google',
            'provider_id' => 'google-123',
        ]);

        $this->mockGoogleUser('google-123', 'Alice', 'alice@example.com');

        $response = $this->get('/api/auth/google/callback');

        $response->assertRedirect();
        $this->assertStringContainsString('/auth/callback?token=', $response->headers->get('Location'));
        $this->assertDatabaseCount('users', 1);
    }

    public function test_google_callback_links_to_existing_email_account(): void
    {
        $existing = User::factory()->create([
            'email' => 'alice@example.com',
            'provider' => null,
            'provider_id' => null,
        ]);

        $this->mockGoogleUser('google-123', 'Alice', 'alice@example.com');

        $response = $this->get('/api/auth/google/callback');

        $response->assertRedirect();
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', [
            'id' => $existing->id,
            'provider' => 'google',
            'provider_id' => 'google-123',
        ]);
    }

    public function test_google_callback_token_is_valid(): void
    {
        $this->mockGoogleUser('google-456', 'Bob', 'bob@example.com');

        $response = $this->get('/api/auth/google/callback');

        $location = $response->headers->get('Location');
        parse_str(parse_url($location, PHP_URL_QUERY), $params);
        $token = $params['token'];

        $this->getJson('/api/user', ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonFragment(['email' => 'bob@example.com']);
    }

    private function mockGoogleUser(string $id, string $name, string $email): void
    {
        $googleUser = Mockery::mock(\Laravel\Socialite\Two\User::class);
        $googleUser->shouldReceive('getId')->andReturn($id);
        $googleUser->shouldReceive('getName')->andReturn($name);
        $googleUser->shouldReceive('getEmail')->andReturn($email);

        Socialite::shouldReceive('driver->stateless->user')->andReturn($googleUser);
    }
}
