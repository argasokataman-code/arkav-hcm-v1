<?php

namespace Tests\Feature;

use App\Mail\ConsentWithdrawalConfirmationMail;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\EmployeeAiConsent;
use App\Models\EmployeeBiometricConsent;
use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class HcmDataPrivacyWithdrawConsentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{token: string, companyId: int, user: User, profile: EmployeeProfile}
     */
    private function employeeContext(): array
    {
        $email = 'privacy.employee@example.com';
        $password = 'StrongPass1';

        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Privacy Employee',
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

    public function test_withdraw_consent_updates_scopes_and_sends_confirmation_email(): void
    {
        Mail::fake();

        $ctx = $this->employeeContext();

        EmployeeAiConsent::query()->create([
            'employee_uuid' => (string) $ctx['profile']->uuid,
            'user_uuid' => (string) $ctx['user']->uuid,
            'consent_given_at' => now()->subDay(),
        ]);

        EmployeeBiometricConsent::query()->create([
            'employee_uuid' => (string) $ctx['profile']->uuid,
            'company_id' => $ctx['companyId'],
            'selfie_consent' => true,
            'gps_consent' => true,
            'consent_given_at' => now()->subDay(),
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$ctx['token'],
            'X-Company-Id' => (string) $ctx['companyId'],
        ])->postJson('/v1/hcm/data-privacy/me/withdraw-consent', [
            'scope' => 'all',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.scope', 'all')
            ->assertJsonPath('data.withdrawn.ai_chat', true)
            ->assertJsonPath('data.withdrawn.biometric', true);

        $this->assertDatabaseHas('employee_ai_consents', [
            'employee_uuid' => (string) $ctx['profile']->uuid,
        ]);
        $this->assertDatabaseHas('employee_biometric_consents', [
            'employee_uuid' => (string) $ctx['profile']->uuid,
            'company_id' => $ctx['companyId'],
            'selfie_consent' => false,
            'gps_consent' => false,
        ]);

        Mail::assertQueued(ConsentWithdrawalConfirmationMail::class, function (ConsentWithdrawalConfirmationMail $mail) use ($ctx): bool {
            return $mail->hasTo($ctx['user']->email)
                && $mail->scope === 'all'
                && $mail->withdrawnScopes['ai_chat'] === true
                && $mail->withdrawnScopes['biometric'] === true;
        });
    }
}
