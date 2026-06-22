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

class PhotoBiometricConsentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{token: string, companyId: int, user: User, profile: EmployeeProfile}
     */
    private function employeeContext(): array
    {
        $email = 'photo.biometric@example.com';
        $password = 'StrongPass1';

        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Photo Employee',
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
    // M6: Profile photo is biometric data (UU PDP Pasal 4 ayat 2)
    // -------------------------------------------------------------------------

    public function test_employee_can_grant_photo_consent(): void
    {
        $ctx = $this->employeeContext();

        $this->withHeaders($this->headers($ctx['token'], $ctx['companyId']))
            ->postJson('/v1/hcm/data-privacy/me/photo-consent')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.photoConsent', true);

        $this->assertDatabaseHas('employee_biometric_consents', [
            'employee_uuid' => (string) $ctx['profile']->uuid,
            'company_id' => $ctx['companyId'],
            'photo_consent' => true,
        ]);
    }

    public function test_employee_can_withdraw_photo_consent(): void
    {
        $ctx = $this->employeeContext();

        // Grant first
        EmployeeBiometricConsent::query()->create([
            'employee_uuid' => (string) $ctx['profile']->uuid,
            'company_id' => $ctx['companyId'],
            'selfie_consent' => false,
            'gps_consent' => false,
            'photo_consent' => true,
            'consent_given_at' => now()->subDay(),
        ]);

        $this->withHeaders($this->headers($ctx['token'], $ctx['companyId']))
            ->deleteJson('/v1/hcm/data-privacy/me/photo-consent')
            ->assertOk()
            ->assertJsonPath('data.withdrawn', true);

        $this->assertDatabaseHas('employee_biometric_consents', [
            'employee_uuid' => (string) $ctx['profile']->uuid,
            'photo_consent' => false,
        ]);
    }

    public function test_biometric_consent_status_includes_photo_consent(): void
    {
        $ctx = $this->employeeContext();

        EmployeeBiometricConsent::query()->create([
            'employee_uuid' => (string) $ctx['profile']->uuid,
            'company_id' => $ctx['companyId'],
            'selfie_consent' => false,
            'gps_consent' => false,
            'photo_consent' => true,
            'consent_given_at' => now(),
        ]);

        $this->withHeaders($this->headers($ctx['token'], $ctx['companyId']))
            ->getJson('/v1/hcm/data-privacy/me/biometric-consent-status')
            ->assertOk()
            ->assertJsonPath('data.biometric.photoConsent', true);
    }

    public function test_photo_consent_requires_authentication(): void
    {
        $this->postJson('/v1/hcm/data-privacy/me/photo-consent')
            ->assertStatus(401);
    }

    public function test_grant_photo_consent_is_idempotent(): void
    {
        $ctx = $this->employeeContext();

        // Grant twice
        $this->withHeaders($this->headers($ctx['token'], $ctx['companyId']))
            ->postJson('/v1/hcm/data-privacy/me/photo-consent')
            ->assertOk();

        $this->withHeaders($this->headers($ctx['token'], $ctx['companyId']))
            ->postJson('/v1/hcm/data-privacy/me/photo-consent')
            ->assertOk();

        // Should be only one record
        $count = EmployeeBiometricConsent::query()
            ->where('employee_uuid', (string) $ctx['profile']->uuid)
            ->where('company_id', $ctx['companyId'])
            ->count();
        $this->assertEquals(1, $count);
    }
}
