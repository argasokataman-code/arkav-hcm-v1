<?php

namespace Tests\Feature;

use App\Models\AuthToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTokenControllerTest extends TestCase
{
    use RefreshDatabase;

    private function cookieName(): string
    {
        return (string) config('auth.api_token_cookie.name', 'arcav_access_token');
    }

    public function test_api_token_endpoint_accepts_valid_api_token_cookie(): void
    {
        $user = User::factory()->create();
        $rawToken = bin2hex(random_bytes(32));

        AuthToken::query()->create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => now()->addDay(),
        ]);

        $response = $this
            ->withHeader('Cookie', $this->cookieName().'='.$rawToken)
            ->getJson('/api-token');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => ['token', 'expiresAt'],
            ]);
    }

    public function test_api_token_endpoint_rejects_missing_token(): void
    {
        $response = $this->getJson('/api-token');

        $response->assertStatus(401);
    }
}
