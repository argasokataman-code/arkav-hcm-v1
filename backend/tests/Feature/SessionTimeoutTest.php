<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SessionTimeoutTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{token: string, companyId: int, user: User, profile: EmployeeProfile}
     */
    private function employeeContext(): array
    {
        $email = 'session.timeout@example.com';
        $password = 'StrongPass1';

        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Session User',
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
    // POST /v1/hcm/data-privacy/me/session-check (re-auth endpoint)
    // -------------------------------------------------------------------------

    public function test_session_check_endpoint_exists_and_validates_password(): void
    {
        $ctx = $this->employeeContext();

        // Wrong password → 422
        $this->withHeaders($this->headers($ctx['token'], $ctx['companyId']))
            ->postJson('/v1/hcm/data-privacy/me/session-check', [
                'password' => 'WrongPassword1',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'INVALID_CREDENTIALS');
    }

    public function test_session_check_with_correct_password_succeeds(): void
    {
        $ctx = $this->employeeContext();

        $this->withHeaders($this->headers($ctx['token'], $ctx['companyId']))
            ->postJson('/v1/hcm/data-privacy/me/session-check', [
                'password' => 'StrongPass1',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.verified', true);
    }

    public function test_session_check_requires_authentication(): void
    {
        $this->postJson('/v1/hcm/data-privacy/me/session-check', [
            'password' => 'StrongPass1',
        ])->assertStatus(401);
    }

    public function test_session_check_requires_password_field(): void
    {
        $ctx = $this->employeeContext();

        $this->withHeaders($this->headers($ctx['token'], $ctx['companyId']))
            ->postJson('/v1/hcm/data-privacy/me/session-check', [])
            ->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Config
    // -------------------------------------------------------------------------

    public function test_pdp_session_timeout_config_exists(): void
    {
        $timeout = config('pdp.session_timeout_minutes');
        $this->assertNotNull($timeout);
        $this->assertIsInt($timeout);
        $this->assertGreaterThan(0, $timeout);
    }

    public function test_pdp_session_timeout_default_is_30_minutes(): void
    {
        // Default should be 30 minutes for sensitive operations
        $this->assertEquals(30, config('pdp.session_timeout_minutes', 30));
    }

    // -------------------------------------------------------------------------
    // Sensitive route last_verified_at tracking
    // -------------------------------------------------------------------------

    public function test_last_verified_at_stored_in_user_session_after_check(): void
    {
        $ctx = $this->employeeContext();

        // Perform session check
        $this->withHeaders($this->headers($ctx['token'], $ctx['companyId']))
            ->postJson('/v1/hcm/data-privacy/me/session-check', [
                'password' => 'StrongPass1',
            ])
            ->assertOk();

        // Verify the user's last_sensitive_verified_at is updated
        $ctx['user']->refresh();
        $this->assertNotNull($ctx['user']->last_sensitive_verified_at);
    }
}
