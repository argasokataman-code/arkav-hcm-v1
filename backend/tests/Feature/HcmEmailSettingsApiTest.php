<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\MailtrapAccountApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use RuntimeException;
use Tests\TestCase;

#[IgnoreDeprecations]
class HcmEmailSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    /**
     * @return array{token: string, companyId: int}
     */
    private function globalAdminContext(string $email = 'email-settings-admin@example.com'): array
    {
        $company = $this->createIsolatedTestCompany();

        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Email Settings Admin',
            'email' => $email,
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $user = User::query()->where('email', $email)->firstOrFail();
        $user->forceFill(['is_super_admin' => true])->save();
        $this->setupHcmAdminPermissions($user, $company);

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => $email,
            'password' => 'StrongPass1',
            'companyCode' => $company->code,
        ])->assertOk();

        return [
            'token' => (string) $login->json('data.accessToken'),
            'companyId' => $company->id,
        ];
    }

    public function test_mailtrap_status_reports_settings_as_credential_source_when_settings_credentials_exist(): void
    {
        Setting::set('email_mailtrap_account_id', '3229', 'email');
        Setting::set('email_mailtrap_api_token', 'fake-token', 'email');

        $ctx = $this->globalAdminContext();

        $mailtrapMock = Mockery::mock(MailtrapAccountApiService::class);
        $mailtrapMock->shouldReceive('testConnection')
            ->once()
            ->with('fake-token', 3229)
            ->andReturn([
                'accountId' => 3229,
                'tokenConfigured' => true,
                'connected' => true,
                'visibleTokens' => [
                    ['id' => 1, 'name' => 'Primary', 'last4' => '1234', 'expiresAt' => null],
                ],
                'visibleTokenCount' => 1,
                'error' => null,
            ]);

        app()->instance(MailtrapAccountApiService::class, $mailtrapMock);

        $this->withHeaders($this->withCompanyContext([
            'Authorization' => 'Bearer '.$ctx['token'],
        ], $ctx['companyId']))
            ->getJson('/v1/hcm/email-settings/mailtrap-status')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.credentialSource', 'settings')
            ->assertJsonPath('data.mode', 'settings')
            ->assertJsonPath('data.connected', true)
            ->assertJsonPath('data.visibleTokenCount', 1);
    }

    public function test_test_connection_endpoint_is_rate_limited_after_five_requests_per_minute(): void
    {
        $ctx = $this->globalAdminContext('email-settings-throttle@example.com');
        $headers = $this->withCompanyContext([
            'Authorization' => 'Bearer '.$ctx['token'],
        ], $ctx['companyId']);

        $payload = [
            'provider' => 'smtp',
            'smtp' => [
                'host' => 'smtp.example.com',
                'username' => 'smtp-user',
                'password' => 'smtp-secret',
            ],
        ];

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->withHeaders($headers)
                ->postJson('/v1/hcm/email-settings/test-connection', $payload)
                ->assertStatus(200);
        }

        $this->withHeaders($headers)
            ->postJson('/v1/hcm/email-settings/test-connection', $payload)
            ->assertStatus(429);
    }

    public function test_mailtrap_status_sanitizes_runtime_exception_message(): void
    {
        Setting::set('email_mailtrap_account_id', '3229', 'email');
        Setting::set('email_mailtrap_api_token', 'fake-token', 'email');

        $ctx = $this->globalAdminContext('email-settings-exception@example.com');

        $mailtrapMock = Mockery::mock(MailtrapAccountApiService::class);
        $mailtrapMock->shouldReceive('testConnection')
            ->once()
            ->with('fake-token', 3229)
            ->andThrow(new RuntimeException('Mailtrap API request failed (401): bearer fake-token rejected'));

        app()->instance(MailtrapAccountApiService::class, $mailtrapMock);

        $this->withHeaders($this->withCompanyContext([
            'Authorization' => 'Bearer '.$ctx['token'],
        ], $ctx['companyId']))
            ->getJson('/v1/hcm/email-settings/mailtrap-status')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.connected', false)
            ->assertJsonPath('data.error.code', 'CONNECTION_FAILED')
            ->assertJsonPath('data.error.message', 'Mailtrap connection failed.');
    }
}