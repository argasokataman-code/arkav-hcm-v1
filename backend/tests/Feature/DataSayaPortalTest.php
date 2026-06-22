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

class DataSayaPortalTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{token: string, companyId: int, user: User, profile: EmployeeProfile}
     */
    private function employeeContext(): array
    {
        $email = 'data.saya@example.com';
        $password = 'StrongPass1';

        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Data Saya User',
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
                'nik' => '3201234567890001',
                'phone' => '081234567890',
                'address' => 'Jl. Merdeka No. 1',
                'base_salary' => 10000000,
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
    // GET /v1/hcm/data-privacy/me/my-data (L2: Data Saya Portal)
    // UU PDP Pasal 8 (hak akses) + Pasal 13 (hak portabilitas)
    // -------------------------------------------------------------------------

    public function test_employee_can_view_own_personal_data(): void
    {
        $ctx = $this->employeeContext();

        $this->withHeaders($this->headers($ctx['token'], $ctx['companyId']))
            ->getJson('/v1/hcm/data-privacy/me/my-data')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'identity',
                    'profile',
                    'consent',
                ],
            ]);
    }

    public function test_my_data_contains_identity_section(): void
    {
        $ctx = $this->employeeContext();

        $response = $this->withHeaders($this->headers($ctx['token'], $ctx['companyId']))
            ->getJson('/v1/hcm/data-privacy/me/my-data');

        $response->assertOk();

        $data = $response->json('data.identity');
        $this->assertEquals('Data Saya User', $data['name']);
        $this->assertEquals('data.saya@example.com', $data['email']);
    }

    public function test_my_data_contains_profile_section(): void
    {
        $ctx = $this->employeeContext();

        $response = $this->withHeaders($this->headers($ctx['token'], $ctx['companyId']))
            ->getJson('/v1/hcm/data-privacy/me/my-data');

        $response->assertOk();

        $data = $response->json('data.profile');
        $this->assertEquals('3201234567890001', $data['nik']);
        $this->assertEquals('081234567890', $data['phone']);
    }

    public function test_my_data_contains_consent_section(): void
    {
        $ctx = $this->employeeContext();

        // Add biometric consent
        EmployeeBiometricConsent::query()->create([
            'employee_uuid' => (string) $ctx['profile']->uuid,
            'company_id' => $ctx['companyId'],
            'selfie_consent' => true,
            'gps_consent' => false,
            'photo_consent' => true,
            'consent_given_at' => now(),
        ]);

        $response = $this->withHeaders($this->headers($ctx['token'], $ctx['companyId']))
            ->getJson('/v1/hcm/data-privacy/me/my-data');

        $response->assertOk();

        $consent = $response->json('data.consent');
        $this->assertNotNull($consent);
        $this->assertTrue($consent['biometric']['selfieConsent']);
        $this->assertTrue($consent['biometric']['photoConsent']);
    }

    public function test_my_data_requires_authentication(): void
    {
        $this->getJson('/v1/hcm/data-privacy/me/my-data')
            ->assertStatus(401);
    }

    public function test_my_data_does_not_leak_other_employee_data(): void
    {
        $ctx1 = $this->employeeContext();

        // Create second employee
        $otherUser = User::factory()->create(['email' => 'other@example.com']);
        $otherCompany = Company::factory()->create();
        $otherProfile = EmployeeProfile::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $otherCompany->id,
            'company_uuid' => $otherCompany->uuid,
            'user_id' => $otherUser->id,
            'user_uuid' => $otherUser->uuid,
            'nik' => '9999999999999999',
        ]);

        $response = $this->withHeaders($this->headers($ctx1['token'], $ctx1['companyId']))
            ->getJson('/v1/hcm/data-privacy/me/my-data');

        $response->assertOk();

        // Should only contain ctx1's data, not other employee's
        $data = $response->json('data.profile');
        $this->assertEquals('3201234567890001', $data['nik']);
        $this->assertNotEquals('9999999999999999', $data['nik']);
    }

    // -------------------------------------------------------------------------
    // GET /v1/hcm/data-privacy/me/my-data/export (portability — Pasal 13)
    // -------------------------------------------------------------------------

    public function test_employee_can_export_own_data_as_json(): void
    {
        $ctx = $this->employeeContext();

        $this->withHeaders($this->headers($ctx['token'], $ctx['companyId']))
            ->getJson('/v1/hcm/data-privacy/me/my-data/export')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.format', 'json');
    }
}
