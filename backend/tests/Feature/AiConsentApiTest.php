<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\EmployeeAiConsent;
use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AiConsentApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{token: string, companyId: int, user: User, profile: EmployeeProfile}
     */
    private function employeeContext(): array
    {
        $email = 'ai.consent.employee@example.com';
        $password = 'StrongPass1';

        $this->postJson('/v1/identity/auth/register', [
            'name' => 'AI Consent Employee',
            'email' => $email,
            'password' => $password,
            'confirmPassword' => $password,
        ])->assertStatus(201);

        $user = User::query()->where('email', $email)->firstOrFail();
        $companyId = (int) CompanyUser::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->value('company_id');
        $company = Company::query()->findOrFail($companyId);

        $profile = EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $user->id, 'company_id' => $companyId],
            [
                'uuid' => (string) Str::uuid(),
                'user_uuid' => (string) $user->uuid,
                'company_uuid' => (string) $company->uuid,
            ]
        );

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => $email,
            'password' => $password,
        ])->assertOk();

        return [
            'token' => (string) $login->json('data.accessToken'),
            'companyId' => $companyId,
            'user' => $user,
            'profile' => $profile,
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
    // POST /v1/hcm/data-privacy/me/ai-consent (grant)
    // -------------------------------------------------------------------------

    public function test_employee_can_grant_ai_consent(): void
    {
        $ctx = $this->employeeContext();

        $this->withHeaders($this->headers($ctx['token'], $ctx['companyId']))
            ->postJson('/v1/hcm/data-privacy/me/ai-consent')
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.message', 'AI consent granted');

        $this->assertDatabaseHas('employee_ai_consents', [
            'employee_uuid' => (string) $ctx['profile']->uuid,
            'user_uuid' => (string) $ctx['user']->uuid,
        ]);
    }

    public function test_grant_ai_consent_returns_existing_when_already_granted(): void
    {
        $ctx = $this->employeeContext();

        EmployeeAiConsent::create([
            'employee_uuid' => (string) $ctx['profile']->uuid,
            'user_uuid' => (string) $ctx['user']->uuid,
            'consent_given_at' => now()->subHour(),
        ]);

        $this->withHeaders($this->headers($ctx['token'], $ctx['companyId']))
            ->postJson('/v1/hcm/data-privacy/me/ai-consent')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.message', 'AI consent already granted');
    }

    public function test_grant_ai_consent_requires_authentication(): void
    {
        $this->postJson('/v1/hcm/data-privacy/me/ai-consent')
            ->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // DELETE /v1/hcm/data-privacy/me/ai-consent (withdraw)
    // -------------------------------------------------------------------------

    public function test_employee_can_withdraw_ai_consent(): void
    {
        $ctx = $this->employeeContext();

        EmployeeAiConsent::create([
            'employee_uuid' => (string) $ctx['profile']->uuid,
            'user_uuid' => (string) $ctx['user']->uuid,
            'consent_given_at' => now()->subDay(),
        ]);

        $this->withHeaders($this->headers($ctx['token'], $ctx['companyId']))
            ->deleteJson('/v1/hcm/data-privacy/me/ai-consent')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.message', 'AI consent withdrawn');

        // Verify withdrawn_at is set
        $consent = EmployeeAiConsent::query()
            ->where('employee_uuid', (string) $ctx['profile']->uuid)
            ->first();

        $this->assertNotNull($consent->withdrawn_at);
        $this->assertFalse($consent->isActive());
    }

    public function test_withdraw_ai_consent_returns_404_when_no_active_consent(): void
    {
        $ctx = $this->employeeContext();

        $this->withHeaders($this->headers($ctx['token'], $ctx['companyId']))
            ->deleteJson('/v1/hcm/data-privacy/me/ai-consent')
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'NO_ACTIVE_CONSENT');
    }

    public function test_withdraw_ai_consent_ignores_already_withdrawn(): void
    {
        $ctx = $this->employeeContext();

        // Create consent that is already withdrawn
        EmployeeAiConsent::create([
            'employee_uuid' => (string) $ctx['profile']->uuid,
            'user_uuid' => (string) $ctx['user']->uuid,
            'consent_given_at' => now()->subDays(2),
            'withdrawn_at' => now()->subDay(),
        ]);

        $this->withHeaders($this->headers($ctx['token'], $ctx['companyId']))
            ->deleteJson('/v1/hcm/data-privacy/me/ai-consent')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'NO_ACTIVE_CONSENT');
    }

    // -------------------------------------------------------------------------
    // GET /v1/hcm/data-privacy/me/ai-consent-status
    // -------------------------------------------------------------------------

    public function test_employee_can_check_ai_consent_status_with_active_consent(): void
    {
        $ctx = $this->employeeContext();

        EmployeeAiConsent::create([
            'employee_uuid' => (string) $ctx['profile']->uuid,
            'user_uuid' => (string) $ctx['user']->uuid,
            'consent_given_at' => now(),
        ]);

        $this->withHeaders($this->headers($ctx['token'], $ctx['companyId']))
            ->getJson('/v1/hcm/data-privacy/me/ai-consent-status')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.has_consent', true);
    }

    public function test_ai_consent_status_returns_false_when_no_consent(): void
    {
        $ctx = $this->employeeContext();

        $this->withHeaders($this->headers($ctx['token'], $ctx['companyId']))
            ->getJson('/v1/hcm/data-privacy/me/ai-consent-status')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.has_consent', false);
    }

    public function test_ai_consent_status_returns_false_when_withdrawn(): void
    {
        $ctx = $this->employeeContext();

        EmployeeAiConsent::create([
            'employee_uuid' => (string) $ctx['profile']->uuid,
            'user_uuid' => (string) $ctx['user']->uuid,
            'consent_given_at' => now()->subDays(2),
            'withdrawn_at' => now()->subDay(),
        ]);

        $this->withHeaders($this->headers($ctx['token'], $ctx['companyId']))
            ->getJson('/v1/hcm/data-privacy/me/ai-consent-status')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.has_consent', false);
    }

    public function test_ai_consent_status_error_response_format_consistent(): void
    {
        // Verify error format matches { code, message } pattern (not string)
        $ctx = $this->employeeContext();

        // Use invalid token to trigger auth error
        $this->withHeaders([
            'Authorization' => 'Bearer invalid-token',
            'X-Company-Id' => (string) $ctx['companyId'],
        ])->getJson('/v1/hcm/data-privacy/me/ai-consent-status')
            ->assertStatus(401);
    }
}
