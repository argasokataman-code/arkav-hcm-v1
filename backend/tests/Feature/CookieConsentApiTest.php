<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CookieConsentApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{token: string, companyId: int, user: User}
     */
    private function userContext(): array
    {
        $email = 'cookie.consent@example.com';
        $password = 'StrongPass1';

        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Cookie User',
            'email' => $email,
            'password' => $password,
            'confirmPassword' => $password,
        ])->assertStatus(201);

        $user = User::query()->where('email', $email)->firstOrFail();
        $companyId = (int) CompanyUser::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->value('company_id');

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => $email,
            'password' => $password,
        ])->assertOk();

        return [
            'token' => (string) $login->json('data.accessToken'),
            'companyId' => $companyId,
            'user' => $user,
        ];
    }

    private function headers(string $token, int $companyId): array
    {
        return [
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $companyId,
        ];
    }

    // -------------------------------------------------------------------------
    // POST /v1/hcm/data-privacy/me/cookie-consent
    // -------------------------------------------------------------------------

    public function test_user_can_save_cookie_consent_accept_all(): void
    {
        $ctx = $this->userContext();

        $this->withHeaders($this->headers($ctx['token'], $ctx['companyId']))
            ->postJson('/v1/hcm/data-privacy/me/cookie-consent', [
                'essential' => true,
                'analytics' => true,
                'marketing' => true,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.essential', true)
            ->assertJsonPath('data.analytics', true)
            ->assertJsonPath('data.marketing', true);
    }

    public function test_user_can_save_cookie_consent_reject_non_essential(): void
    {
        $ctx = $this->userContext();

        $this->withHeaders($this->headers($ctx['token'], $ctx['companyId']))
            ->postJson('/v1/hcm/data-privacy/me/cookie-consent', [
                'essential' => true,
                'analytics' => false,
                'marketing' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.essential', true)
            ->assertJsonPath('data.analytics', false)
            ->assertJsonPath('data.marketing', false);
    }

    public function test_essential_cookies_cannot_be_rejected(): void
    {
        $ctx = $this->userContext();

        // Even if user sends essential=false, it should be forced to true
        $this->withHeaders($this->headers($ctx['token'], $ctx['companyId']))
            ->postJson('/v1/hcm/data-privacy/me/cookie-consent', [
                'essential' => false,
                'analytics' => false,
                'marketing' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.essential', true);
    }

    public function test_cookie_consent_requires_boolean_fields(): void
    {
        $ctx = $this->userContext();

        $this->withHeaders($this->headers($ctx['token'], $ctx['companyId']))
            ->postJson('/v1/hcm/data-privacy/me/cookie-consent', [
                'analytics' => 'not-a-boolean',
            ])
            ->assertStatus(422);
    }

    public function test_cookie_consent_updates_existing_preferences(): void
    {
        $ctx = $this->userContext();

        // First save
        $this->withHeaders($this->headers($ctx['token'], $ctx['companyId']))
            ->postJson('/v1/hcm/data-privacy/me/cookie-consent', [
                'essential' => true,
                'analytics' => true,
                'marketing' => false,
            ])
            ->assertOk();

        // Update
        $this->withHeaders($this->headers($ctx['token'], $ctx['companyId']))
            ->postJson('/v1/hcm/data-privacy/me/cookie-consent', [
                'essential' => true,
                'analytics' => false,
                'marketing' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.analytics', false)
            ->assertJsonPath('data.marketing', true);
    }

    public function test_cookie_consent_requires_authentication(): void
    {
        $this->postJson('/v1/hcm/data-privacy/me/cookie-consent', [
            'essential' => true,
        ])->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // GET /v1/hcm/data-privacy/me/cookie-consent
    // -------------------------------------------------------------------------

    public function test_user_can_retrieve_cookie_consent(): void
    {
        $ctx = $this->userContext();

        // Save first
        $this->withHeaders($this->headers($ctx['token'], $ctx['companyId']))
            ->postJson('/v1/hcm/data-privacy/me/cookie-consent', [
                'essential' => true,
                'analytics' => true,
                'marketing' => false,
            ])
            ->assertOk();

        // Retrieve
        $this->withHeaders($this->headers($ctx['token'], $ctx['companyId']))
            ->getJson('/v1/hcm/data-privacy/me/cookie-consent')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.essential', true)
            ->assertJsonPath('data.analytics', true)
            ->assertJsonPath('data.marketing', false);
    }

    public function test_cookie_consent_returns_null_when_no_preferences_saved(): void
    {
        $ctx = $this->userContext();

        $this->withHeaders($this->headers($ctx['token'], $ctx['companyId']))
            ->getJson('/v1/hcm/data-privacy/me/cookie-consent')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', null);
    }
}
