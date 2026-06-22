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

class WithdrawConsentScopeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{token: string, companyId: int, user: User, profile: EmployeeProfile}
     */
    private function employeeContext(): array
    {
        $email = 'withdraw.scope@example.com';
        $password = 'StrongPass1';

        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Scope Employee',
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

    private function seedConsents(EmployeeProfile $profile, User $user, int $companyId): void
    {
        EmployeeAiConsent::query()->create([
            'employee_uuid' => (string) $profile->uuid,
            'user_uuid' => (string) $user->uuid,
            'consent_given_at' => now()->subDay(),
        ]);

        EmployeeBiometricConsent::query()->create([
            'employee_uuid' => (string) $profile->uuid,
            'company_id' => $companyId,
            'selfie_consent' => true,
            'gps_consent' => true,
            'consent_given_at' => now()->subDay(),
        ]);
    }

    // -------------------------------------------------------------------------
    // scope: ai_chat only
    // -------------------------------------------------------------------------

    public function test_withdraw_scope_ai_chat_only_withdraws_ai_consent(): void
    {
        Mail::fake();

        $ctx = $this->employeeContext();
        $this->seedConsents($ctx['profile'], $ctx['user'], $ctx['companyId']);

        $this->withHeaders($this->headers($ctx['token'], $ctx['companyId']))
            ->postJson('/v1/hcm/data-privacy/me/withdraw-consent', [
                'scope' => 'ai_chat',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.scope', 'ai_chat')
            ->assertJsonPath('data.withdrawn.ai_chat', true)
            ->assertJsonPath('data.withdrawn.biometric', false);

        // AI consent should be withdrawn
        $aiConsent = EmployeeAiConsent::query()
            ->where('employee_uuid', (string) $ctx['profile']->uuid)
            ->first();
        $this->assertNotNull($aiConsent->withdrawn_at);

        // Biometric consent should NOT be affected
        $bioConsent = EmployeeBiometricConsent::query()
            ->where('employee_uuid', (string) $ctx['profile']->uuid)
            ->where('company_id', $ctx['companyId'])
            ->first();
        $this->assertTrue((bool) $bioConsent->selfie_consent);
        $this->assertTrue((bool) $bioConsent->gps_consent);
        $this->assertNull($bioConsent->consent_withdrawn_at);

        // Email should be queued with correct scope
        Mail::assertQueued(ConsentWithdrawalConfirmationMail::class, function ($mail) use ($ctx): bool {
            return $mail->hasTo($ctx['user']->email)
                && $mail->scope === 'ai_chat'
                && $mail->withdrawnScopes['ai_chat'] === true
                && $mail->withdrawnScopes['biometric'] === false;
        });
    }

    // -------------------------------------------------------------------------
    // scope: biometric only
    // -------------------------------------------------------------------------

    public function test_withdraw_scope_biometric_only_withdraws_biometric_consent(): void
    {
        Mail::fake();

        $ctx = $this->employeeContext();
        $this->seedConsents($ctx['profile'], $ctx['user'], $ctx['companyId']);

        $this->withHeaders($this->headers($ctx['token'], $ctx['companyId']))
            ->postJson('/v1/hcm/data-privacy/me/withdraw-consent', [
                'scope' => 'biometric',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.scope', 'biometric')
            ->assertJsonPath('data.withdrawn.ai_chat', false)
            ->assertJsonPath('data.withdrawn.biometric', true);

        // AI consent should NOT be affected
        $aiConsent = EmployeeAiConsent::query()
            ->where('employee_uuid', (string) $ctx['profile']->uuid)
            ->first();
        $this->assertNull($aiConsent->withdrawn_at);
        $this->assertTrue($aiConsent->isActive());

        // Biometric consent should be withdrawn
        $bioConsent = EmployeeBiometricConsent::query()
            ->where('employee_uuid', (string) $ctx['profile']->uuid)
            ->where('company_id', $ctx['companyId'])
            ->first();
        $this->assertFalse((bool) $bioConsent->selfie_consent);
        $this->assertFalse((bool) $bioConsent->gps_consent);
        $this->assertNotNull($bioConsent->consent_withdrawn_at);

        // Email with correct scope
        Mail::assertQueued(ConsentWithdrawalConfirmationMail::class, function ($mail) use ($ctx): bool {
            return $mail->hasTo($ctx['user']->email)
                && $mail->scope === 'biometric'
                && $mail->withdrawnScopes['ai_chat'] === false
                && $mail->withdrawnScopes['biometric'] === true;
        });
    }

    // -------------------------------------------------------------------------
    // scope: all (already tested but re-verify for completeness)
    // -------------------------------------------------------------------------

    public function test_withdraw_scope_all_withdraws_both_consents(): void
    {
        Mail::fake();

        $ctx = $this->employeeContext();
        $this->seedConsents($ctx['profile'], $ctx['user'], $ctx['companyId']);

        $this->withHeaders($this->headers($ctx['token'], $ctx['companyId']))
            ->postJson('/v1/hcm/data-privacy/me/withdraw-consent', [
                'scope' => 'all',
            ])
            ->assertOk()
            ->assertJsonPath('data.withdrawn.ai_chat', true)
            ->assertJsonPath('data.withdrawn.biometric', true);

        Mail::assertQueued(ConsentWithdrawalConfirmationMail::class);
    }

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

    public function test_withdraw_consent_rejects_invalid_scope(): void
    {
        $ctx = $this->employeeContext();

        $this->withHeaders($this->headers($ctx['token'], $ctx['companyId']))
            ->postJson('/v1/hcm/data-privacy/me/withdraw-consent', [
                'scope' => 'invalid_scope',
            ])
            ->assertStatus(422);
    }

    public function test_withdraw_consent_requires_scope_field(): void
    {
        $ctx = $this->employeeContext();

        $this->withHeaders($this->headers($ctx['token'], $ctx['companyId']))
            ->postJson('/v1/hcm/data-privacy/me/withdraw-consent', [])
            ->assertStatus(422);
    }
}
