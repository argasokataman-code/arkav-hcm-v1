<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\EmployeeBiometricConsent;
use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BiometricConsentApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{token: string, companyId: int, user: User, profile: EmployeeProfile}
     */
    private function employeeContext(): array
    {
        $email = 'biometric.employee@example.com';
        $password = 'StrongPass1';

        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Biometric Employee',
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
    // POST /v1/hcm/data-privacy/me/biometric-consent (grant)
    // -------------------------------------------------------------------------

    public function test_employee_can_grant_biometric_consent(): void
    {
        $ctx = $this->employeeContext();

        $this->withHeaders($this->headers($ctx['token'], $ctx['companyId']))
            ->postJson('/v1/hcm/data-privacy/me/biometric-consent', [
                'selfie_consent' => true,
                'gps_consent' => true,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.selfieConsent', true)
            ->assertJsonPath('data.gpsConsent', true);

        $this->assertDatabaseHas('employee_biometric_consents', [
            'employee_uuid' => (string) $ctx['profile']->uuid,
            'company_id' => $ctx['companyId'],
            'selfie_consent' => true,
            'gps_consent' => true,
        ]);
    }

    public function test_grant_biometric_consent_requires_selfie_and_gps_fields(): void
    {
        $ctx = $this->employeeContext();

        $this->withHeaders($this->headers($ctx['token'], $ctx['companyId']))
            ->postJson('/v1/hcm/data-privacy/me/biometric-consent', [])
            ->assertStatus(422);
    }

    public function test_grant_biometric_consent_idempotent_update_or_create(): void
    {
        $ctx = $this->employeeContext();

        // First grant
        $this->withHeaders($this->headers($ctx['token'], $ctx['companyId']))
            ->postJson('/v1/hcm/data-privacy/me/biometric-consent', [
                'selfie_consent' => true,
                'gps_consent' => false,
            ])
            ->assertOk();

        // Second grant (update)
        $this->withHeaders($this->headers($ctx['token'], $ctx['companyId']))
            ->postJson('/v1/hcm/data-privacy/me/biometric-consent', [
                'selfie_consent' => true,
                'gps_consent' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.gpsConsent', true);

        // Should be only one record
        $count = EmployeeBiometricConsent::query()
            ->where('employee_uuid', (string) $ctx['profile']->uuid)
            ->count();
        $this->assertEquals(1, $count);
    }

    public function test_grant_biometric_consent_requires_authentication(): void
    {
        $this->postJson('/v1/hcm/data-privacy/me/biometric-consent', [
            'selfie_consent' => true,
            'gps_consent' => true,
        ])->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // DELETE /v1/hcm/data-privacy/me/biometric-consent (withdraw)
    // -------------------------------------------------------------------------

    public function test_employee_can_withdraw_biometric_consent(): void
    {
        $ctx = $this->employeeContext();

        // First grant
        EmployeeBiometricConsent::query()->create([
            'employee_uuid' => (string) $ctx['profile']->uuid,
            'company_id' => $ctx['companyId'],
            'selfie_consent' => true,
            'gps_consent' => true,
            'consent_given_at' => now()->subDay(),
        ]);

        $this->withHeaders($this->headers($ctx['token'], $ctx['companyId']))
            ->deleteJson('/v1/hcm/data-privacy/me/biometric-consent')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.withdrawn', true);

        $this->assertDatabaseHas('employee_biometric_consents', [
            'employee_uuid' => (string) $ctx['profile']->uuid,
            'company_id' => $ctx['companyId'],
            'selfie_consent' => false,
            'gps_consent' => false,
        ]);
    }

    // -------------------------------------------------------------------------
    // GET /v1/hcm/data-privacy/me/biometric-consent-status
    // -------------------------------------------------------------------------

    public function test_employee_can_check_biometric_consent_status(): void
    {
        $ctx = $this->employeeContext();

        EmployeeBiometricConsent::query()->create([
            'employee_uuid' => (string) $ctx['profile']->uuid,
            'company_id' => $ctx['companyId'],
            'selfie_consent' => true,
            'gps_consent' => false,
            'consent_given_at' => now(),
        ]);

        $this->withHeaders($this->headers($ctx['token'], $ctx['companyId']))
            ->getJson('/v1/hcm/data-privacy/me/biometric-consent-status')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.biometric.selfieConsent', true)
            ->assertJsonPath('data.biometric.gpsConsent', false);
    }

    public function test_biometric_consent_status_returns_null_when_no_consent(): void
    {
        $ctx = $this->employeeContext();

        $this->withHeaders($this->headers($ctx['token'], $ctx['companyId']))
            ->getJson('/v1/hcm/data-privacy/me/biometric-consent-status')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.biometric', null);
    }

    public function test_biometric_consent_status_shows_withdrawn_state(): void
    {
        $ctx = $this->employeeContext();

        EmployeeBiometricConsent::query()->create([
            'employee_uuid' => (string) $ctx['profile']->uuid,
            'company_id' => $ctx['companyId'],
            'selfie_consent' => false,
            'gps_consent' => false,
            'consent_given_at' => now()->subDays(2),
            'consent_withdrawn_at' => now(),
        ]);

        $this->withHeaders($this->headers($ctx['token'], $ctx['companyId']))
            ->getJson('/v1/hcm/data-privacy/me/biometric-consent-status')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.biometric.selfieConsent', false)
            ->assertJsonPath('data.biometric.gpsConsent', false);

        // consentWithdrawnAt should be present
        $response = $this->withHeaders($this->headers($ctx['token'], $ctx['companyId']))
            ->getJson('/v1/hcm/data-privacy/me/biometric-consent-status');

        $this->assertNotNull($response->json('data.biometric.consentWithdrawnAt'));
    }
}
