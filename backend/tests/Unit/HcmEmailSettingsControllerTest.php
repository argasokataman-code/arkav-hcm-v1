<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\HcmEmailSettingsController;
use App\Models\Setting;
use App\Services\MailtrapAccountApiService;
use App\Services\SmtpConnectionProbeService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class HcmEmailSettingsControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table): void {
                $table->id();
                $table->string('key')->unique()->index();
                $table->text('value')->nullable();
                $table->string('group')->default('general')->index();
                $table->timestamps();
            });
        }

        Setting::query()->where('group', 'email')->delete();
        Cache::flush();
    }

    public function test_mailtrap_status_returns_connected_for_global_admin(): void
    {
        config()->set('services.mailtrap.api_token', 'a1b2c3d4e5f6');
        config()->set('services.mailtrap.account_id', 3229);

        $user = new class {
            public function isGlobalHcmAdmin(): bool
            {
                return true;
            }
        };

        $request = Request::create('/v1/hcm/email-settings/mailtrap-status', 'GET');
        $request->setUserResolver(static fn () => $user);

        $service = Mockery::mock(MailtrapAccountApiService::class);
        $service->shouldReceive('testConnection')->once()->with('a1b2c3d4e5f6', 3229)->andReturn([
            'provider' => 'mailtrap',
            'mode' => 'ephemeral',
            'persisted' => false,
            'accountId' => 3229,
            'tokenConfigured' => true,
            'connected' => true,
            'visibleTokenCount' => 1,
            'visibleTokens' => [
                [
                    'id' => 12345,
                    'name' => 'My API Token',
                    'last4' => 'x7k9',
                    'expiresAt' => null,
                ],
            ],
            'error' => null,
        ]);

        $response = (new HcmEmailSettingsController())->mailtrapStatus($request, $service);

        $this->assertSame(200, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertTrue($payload['success']);
        $this->assertTrue($payload['data']['connected']);
        $this->assertSame('env', $payload['data']['credentialSource']);
        $this->assertSame(1, $payload['data']['visibleTokenCount']);
        $this->assertSame('e5f6', $payload['data']['tokenLast4']);
    }

    public function test_mailtrap_status_returns_forbidden_for_non_admin(): void
    {
        $user = new class {
            public function isGlobalHcmAdmin(): bool
            {
                return false;
            }
        };

        $request = Request::create('/v1/hcm/email-settings/mailtrap-status', 'GET');
        $request->setUserResolver(static fn () => $user);

        $service = Mockery::mock(MailtrapAccountApiService::class);

        $response = (new HcmEmailSettingsController())->mailtrapStatus($request, $service);

        $this->assertSame(403, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertFalse($payload['success']);
        $this->assertSame('ADMIN_REQUIRED', $payload['error']['code']);
    }

    public function test_mailtrap_status_returns_forbidden_for_tenant_hcm_admin_but_not_global(): void
    {
        $user = new class {
            public function isGlobalHcmAdmin(): bool
            {
                return false;
            }

            public function isHcmAdmin(): bool
            {
                return true;
            }
        };

        $request = Request::create('/v1/hcm/email-settings/mailtrap-status', 'GET');
        $request->setUserResolver(static fn () => $user);

        $service = Mockery::mock(MailtrapAccountApiService::class);

        $response = (new HcmEmailSettingsController())->mailtrapStatus($request, $service);

        $this->assertSame(403, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertFalse($payload['success']);
        $this->assertSame('ADMIN_REQUIRED', $payload['error']['code']);
    }

    public function test_mailtrap_status_handles_runtime_exception(): void
    {
        config()->set('services.mailtrap.api_token', 'a1b2c3d4e5f6');
        config()->set('services.mailtrap.account_id', 3229);

        $user = new class {
            public function isGlobalHcmAdmin(): bool
            {
                return true;
            }
        };

        $request = Request::create('/v1/hcm/email-settings/mailtrap-status', 'GET');
        $request->setUserResolver(static fn () => $user);

        $service = Mockery::mock(MailtrapAccountApiService::class);
        $service->shouldReceive('testConnection')->once()->with('a1b2c3d4e5f6', 3229)->andThrow(new RuntimeException('Mailtrap API request failed (401): Unauthorized'));

        $response = (new HcmEmailSettingsController())->mailtrapStatus($request, $service);

        $this->assertSame(200, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertTrue($payload['success']);
        $this->assertFalse($payload['data']['connected']);
        $this->assertSame('Mailtrap API request failed (401): Unauthorized', $payload['data']['error']);
    }

    public function test_mailtrap_status_prefers_saved_settings_credentials_over_env(): void
    {
        config()->set('services.mailtrap.api_token', 'env-token-9999');
        config()->set('services.mailtrap.account_id', 4001);
        Setting::set('email_mailtrap_account_id', '2682142', 'email');
        Setting::set('email_mailtrap_api_token', 'mailtrap-token-1234', 'email');

        $user = new class {
            public function isGlobalHcmAdmin(): bool
            {
                return true;
            }
        };

        $request = Request::create('/v1/hcm/email-settings/mailtrap-status', 'GET');
        $request->setUserResolver(static fn () => $user);

        $service = Mockery::mock(MailtrapAccountApiService::class);
        $service->shouldReceive('testConnection')->once()->with('mailtrap-token-1234', 2682142)->andReturn([
            'provider' => 'mailtrap',
            'mode' => 'ephemeral',
            'persisted' => false,
            'accountId' => 2682142,
            'tokenConfigured' => true,
            'connected' => true,
            'visibleTokenCount' => 0,
            'visibleTokens' => [],
            'error' => null,
        ]);

        $response = (new HcmEmailSettingsController())->mailtrapStatus($request, $service);

        $this->assertSame(200, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertTrue($payload['success']);
        $this->assertTrue($payload['data']['connected']);
        $this->assertSame('settings', $payload['data']['credentialSource']);
        $this->assertSame(2682142, $payload['data']['accountId']);
        $this->assertSame('1234', $payload['data']['tokenLast4']);
    }

    public function test_email_settings_profile_returns_masked_credentials_for_global_admin(): void
    {
        Setting::set('email_provider', 'smtp', 'email');
        Setting::set('email_from_address', 'noreply@example.com', 'email');
        Setting::set('email_from_name', 'Arkav System', 'email');
        Setting::set('email_smtp_host', 'smtp.example.com', 'email');
        Setting::set('email_smtp_port', '587', 'email');
        Setting::set('email_smtp_encryption', 'tls', 'email');
        Setting::set('email_smtp_username', 'smtp-user', 'email');
        Setting::set('email_smtp_password', 'smtp-secret-1234', 'email');
        Setting::set('email_mailtrap_account_id', '3229', 'email');
        Setting::set('email_mailtrap_api_token', 'mailtrap-token-7890', 'email');

        $user = new class {
            public function isGlobalHcmAdmin(): bool
            {
                return true;
            }
        };

        $request = Request::create('/v1/hcm/email-settings', 'GET');
        $request->setUserResolver(static fn () => $user);

        $response = (new HcmEmailSettingsController())->show($request);

        $this->assertSame(200, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertTrue($payload['success']);
        $this->assertSame('smtp', $payload['data']['provider']);
        $this->assertSame('noreply@example.com', $payload['data']['fromAddress']);
        $this->assertSame('smtp.example.com', $payload['data']['smtp']['host']);
        $this->assertSame(587, $payload['data']['smtp']['port']);
        $this->assertSame('****1234', $payload['data']['smtp']['passwordMasked']);
        $this->assertTrue($payload['data']['smtp']['configured']);
        $this->assertSame(3229, $payload['data']['mailtrap']['accountId']);
        $this->assertSame('****7890', $payload['data']['mailtrap']['apiTokenMasked']);
        $this->assertTrue($payload['data']['mailtrap']['configured']);
    }

    public function test_email_settings_profile_forbidden_for_non_global_admin(): void
    {
        $user = new class {
            public function isGlobalHcmAdmin(): bool
            {
                return false;
            }
        };

        $request = Request::create('/v1/hcm/email-settings', 'GET');
        $request->setUserResolver(static fn () => $user);

        $response = (new HcmEmailSettingsController())->show($request);

        $this->assertSame(403, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertFalse($payload['success']);
        $this->assertSame('ADMIN_REQUIRED', $payload['error']['code']);
    }

    public function test_email_settings_update_smtp_profile_for_global_admin(): void
    {
        $user = new class {
            public int $id = 99;
            public string $uuid = 'c89e4b80-fb84-4f7b-98ba-7d4dc358dc32';
            public string $email = 'global-admin@example.com';

            public function isGlobalHcmAdmin(): bool
            {
                return true;
            }
        };

        $request = Request::create('/v1/hcm/email-settings', 'PUT', [
            'provider' => 'smtp',
            'fromAddress' => 'noreply@example.com',
            'fromName' => 'Arkav Mail',
            'smtp' => [
                'host' => 'smtp.example.com',
                'port' => 465,
                'encryption' => 'ssl',
                'username' => 'smtp-user',
                'password' => 'smtp-password-5678',
            ],
        ]);
        $request->setUserResolver(static fn () => $user);

        $response = (new HcmEmailSettingsController())->update($request);

        $this->assertSame(200, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertTrue($payload['success']);
        $this->assertSame('smtp', $payload['data']['provider']);
        $this->assertSame('smtp.example.com', $payload['data']['smtp']['host']);
        $this->assertSame('****5678', $payload['data']['smtp']['passwordMasked']);
        $this->assertSame('global-admin@example.com', $payload['meta']['updatedBy']['email']);

        $this->assertSame('smtp', Setting::get('email_provider'));
        $this->assertSame('smtp.example.com', Setting::get('email_smtp_host'));
        $this->assertSame(465, Setting::get('email_smtp_port'));
        $this->assertSame('ssl', Setting::get('email_smtp_encryption'));
        $this->assertSame(99, Setting::get('email_last_updated_by_id'));
        $this->assertSame('c89e4b80-fb84-4f7b-98ba-7d4dc358dc32', Setting::get('email_last_updated_by_uuid'));
        $this->assertSame('global-admin@example.com', Setting::get('email_last_updated_by_email'));
        $this->assertNotNull(Setting::get('email_last_updated_at'));
    }

    public function test_email_settings_update_mailtrap_profile_for_global_admin(): void
    {
        $user = new class {
            public function isGlobalHcmAdmin(): bool
            {
                return true;
            }
        };

        $request = Request::create('/v1/hcm/email-settings', 'PUT', [
            'provider' => 'mailtrap',
            'mailtrap' => [
                'accountId' => 3229,
                'apiToken' => 'mailtrap-key-1234',
            ],
        ]);
        $request->setUserResolver(static fn () => $user);

        $response = (new HcmEmailSettingsController())->update($request);

        $this->assertSame(200, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertTrue($payload['success']);
        $this->assertSame('mailtrap', $payload['data']['provider']);
        $this->assertSame(3229, $payload['data']['mailtrap']['accountId']);
        $this->assertSame('****1234', $payload['data']['mailtrap']['apiTokenMasked']);

        $this->assertSame('mailtrap', Setting::get('email_provider'));
        $this->assertSame(3229, Setting::get('email_mailtrap_account_id'));
    }

    public function test_email_settings_update_returns_validation_error_for_invalid_payload(): void
    {
        $user = new class {
            public function isGlobalHcmAdmin(): bool
            {
                return true;
            }
        };

        $request = Request::create('/v1/hcm/email-settings', 'PUT', [
            'provider' => 'smtp',
            'fromAddress' => 'not-an-email',
            'smtp' => [
                'host' => '',
                'username' => '',
            ],
        ]);
        $request->setUserResolver(static fn () => $user);

        $response = (new HcmEmailSettingsController())->update($request);

        $this->assertSame(422, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertFalse($payload['success']);
        $this->assertSame('VALIDATION_ERROR', $payload['error']['code']);
    }

    public function test_email_settings_update_forbidden_for_non_global_admin(): void
    {
        $user = new class {
            public function isGlobalHcmAdmin(): bool
            {
                return false;
            }
        };

        $request = Request::create('/v1/hcm/email-settings', 'PUT', [
            'provider' => 'smtp',
            'smtp' => [
                'host' => 'smtp.example.com',
                'username' => 'smtp-user',
            ],
        ]);
        $request->setUserResolver(static fn () => $user);

        $response = (new HcmEmailSettingsController())->update($request);

        $this->assertSame(403, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertFalse($payload['success']);
        $this->assertSame('ADMIN_REQUIRED', $payload['error']['code']);
    }

    public function test_email_settings_test_connection_returns_smtp_success_payload(): void
    {
        $user = new class {
            public function isGlobalHcmAdmin(): bool
            {
                return true;
            }
        };

        $request = Request::create('/v1/hcm/email-settings/test-connection', 'POST', [
            'provider' => 'smtp',
            'timeout' => 7,
            'smtp' => [
                'host' => 'smtp.example.com',
                'port' => 587,
                'encryption' => 'tls',
                'username' => 'smtp-user',
                'password' => 'smtp-secret',
            ],
        ]);
        $request->setUserResolver(static fn () => $user);

        $smtpProbe = Mockery::mock(SmtpConnectionProbeService::class);
        $smtpProbe->shouldReceive('probe')->once()->andReturn([
            'provider' => 'smtp',
            'mode' => 'ephemeral',
            'persisted' => false,
            'connected' => true,
            'testedAt' => now()->toIso8601String(),
            'details' => [
                'host' => 'smtp.example.com',
                'port' => 587,
                'encryption' => 'tls',
                'username' => 'smtp-user',
                'timeout' => 7,
            ],
            'error' => null,
        ]);
        $mailtrapService = Mockery::mock(MailtrapAccountApiService::class);

        $response = (new HcmEmailSettingsController())->testConnection($request, $smtpProbe, $mailtrapService);

        $this->assertSame(200, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertTrue($payload['success']);
        $this->assertTrue($payload['data']['connected']);
        $this->assertFalse($payload['data']['persisted']);
        $this->assertSame('smtp', $payload['meta']['lastTestStatus']['provider']);
        $this->assertTrue($payload['meta']['lastTestStatus']['connected']);
    }

    public function test_email_settings_test_connection_returns_mailtrap_auth_failure_payload(): void
    {
        $user = new class {
            public function isGlobalHcmAdmin(): bool
            {
                return true;
            }
        };

        $request = Request::create('/v1/hcm/email-settings/test-connection', 'POST', [
            'provider' => 'mailtrap',
            'mailtrap' => [
                'accountId' => 3229,
                'apiToken' => 'bad-token',
            ],
        ]);
        $request->setUserResolver(static fn () => $user);

        $smtpProbe = Mockery::mock(SmtpConnectionProbeService::class);
        $mailtrapService = Mockery::mock(MailtrapAccountApiService::class);
        $mailtrapService->shouldReceive('testConnection')->once()->andReturn([
            'provider' => 'mailtrap',
            'mode' => 'ephemeral',
            'persisted' => false,
            'accountId' => 3229,
            'tokenConfigured' => true,
            'connected' => false,
            'visibleTokenCount' => 0,
            'visibleTokens' => [],
            'testedAt' => now()->toIso8601String(),
            'error' => [
                'code' => 'AUTH_FAILED',
                'message' => 'Mailtrap authentication failed.',
            ],
        ]);

        $response = (new HcmEmailSettingsController())->testConnection($request, $smtpProbe, $mailtrapService);

        $this->assertSame(200, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertTrue($payload['success']);
        $this->assertFalse($payload['data']['connected']);
        $this->assertSame('AUTH_FAILED', $payload['data']['error']['code']);
        $this->assertSame('mailtrap', $payload['meta']['lastTestStatus']['provider']);
        $this->assertSame('AUTH_FAILED', $payload['meta']['lastTestStatus']['error']['code']);
    }

    public function test_email_settings_test_connection_returns_validation_error_for_missing_smtp_password(): void
    {
        $user = new class {
            public function isGlobalHcmAdmin(): bool
            {
                return true;
            }
        };

        $request = Request::create('/v1/hcm/email-settings/test-connection', 'POST', [
            'provider' => 'smtp',
            'smtp' => [
                'host' => 'smtp.example.com',
                'username' => 'smtp-user',
                'password' => '',
            ],
        ]);
        $request->setUserResolver(static fn () => $user);

        $smtpProbe = Mockery::mock(SmtpConnectionProbeService::class);
        $mailtrapService = Mockery::mock(MailtrapAccountApiService::class);

        $response = (new HcmEmailSettingsController())->testConnection($request, $smtpProbe, $mailtrapService);

        $this->assertSame(422, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertFalse($payload['success']);
        $this->assertSame('VALIDATION_ERROR', $payload['error']['code']);
    }

    public function test_email_settings_test_connection_returns_timeout_failure_payload(): void
    {
        $user = new class {
            public function isGlobalHcmAdmin(): bool
            {
                return true;
            }
        };

        $request = Request::create('/v1/hcm/email-settings/test-connection', 'POST', [
            'provider' => 'smtp',
            'smtp' => [
                'host' => 'smtp.example.com',
                'username' => 'smtp-user',
                'password' => 'smtp-secret',
            ],
        ]);
        $request->setUserResolver(static fn () => $user);

        $smtpProbe = Mockery::mock(SmtpConnectionProbeService::class);
        $smtpProbe->shouldReceive('probe')->once()->andReturn([
            'provider' => 'smtp',
            'mode' => 'ephemeral',
            'persisted' => false,
            'connected' => false,
            'testedAt' => now()->toIso8601String(),
            'details' => [
                'host' => 'smtp.example.com',
                'port' => 587,
                'encryption' => null,
                'username' => 'smtp-user',
                'timeout' => 10,
            ],
            'error' => [
                'code' => 'TIMEOUT',
                'message' => 'SMTP connection timed out.',
            ],
        ]);
        $mailtrapService = Mockery::mock(MailtrapAccountApiService::class);

        $response = (new HcmEmailSettingsController())->testConnection($request, $smtpProbe, $mailtrapService);

        $this->assertSame(200, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertTrue($payload['success']);
        $this->assertFalse($payload['data']['connected']);
        $this->assertSame('TIMEOUT', $payload['data']['error']['code']);
        $this->assertSame('TIMEOUT', Setting::get('email_last_test_error_code'));
        $this->assertSame('smtp', Setting::get('email_last_test_provider'));
    }
}
