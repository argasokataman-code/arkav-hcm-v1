<?php

namespace Tests\Unit;

use App\Models\Setting;
use App\Services\EmailSettingsService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EmailSettingsServiceTest extends TestCase
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
    }

    public function test_update_profile_encrypts_secrets_at_rest_and_masks_response(): void
    {
        $service = new EmailSettingsService();

        $user = new class {
            public int $id = 101;
            public string $uuid = '1f9cfde4-8085-4c79-b4b7-4c63d4210268';
            public string $email = 'owner@example.com';
        };

        $result = $service->updateProfile([
            'provider' => 'smtp',
            'fromAddress' => 'noreply@example.com',
            'fromName' => 'Arkav Mail',
            'smtp' => [
                'host' => 'smtp.example.com',
                'port' => 587,
                'encryption' => 'tls',
                'username' => 'smtp-user',
                'password' => 'smtp-secret-1234',
            ],
            'mailtrap' => [
                'accountId' => 3229,
                'apiToken' => 'mailtrap-token-7890',
            ],
        ], $user);

        $storedSmtp = (string) Setting::get('email_smtp_password');
        $storedToken = (string) Setting::get('email_mailtrap_api_token');

        $this->assertStringStartsWith('enc::', $storedSmtp);
        $this->assertStringStartsWith('enc::', $storedToken);
        $this->assertStringNotContainsString('smtp-secret-1234', $storedSmtp);
        $this->assertStringNotContainsString('mailtrap-token-7890', $storedToken);

        $this->assertSame('****1234', $result['data']['smtp']['passwordMasked']);
        $this->assertSame('****7890', $result['data']['mailtrap']['apiTokenMasked']);
        $this->assertTrue($result['data']['smtp']['configured']);
        $this->assertTrue($result['data']['mailtrap']['configured']);
        $this->assertSame(101, $result['meta']['updatedBy']['id']);
        $this->assertSame('owner@example.com', $result['meta']['updatedBy']['email']);
        $this->assertSame(101, Setting::get('email_last_updated_by_id'));
        $this->assertSame('1f9cfde4-8085-4c79-b4b7-4c63d4210268', Setting::get('email_last_updated_by_uuid'));
        $this->assertSame('owner@example.com', Setting::get('email_last_updated_by_email'));
        $this->assertNotNull(Setting::get('email_last_updated_at'));
    }

    public function test_get_profile_supports_legacy_plaintext_secrets_for_backward_compatibility(): void
    {
        Setting::set('email_provider', 'smtp', 'email');
        Setting::set('email_smtp_host', 'smtp.example.com', 'email');
        Setting::set('email_smtp_username', 'smtp-user', 'email');
        Setting::set('email_smtp_password', 'legacy-plain-4444', 'email');
        Setting::set('email_mailtrap_account_id', '3229', 'email');
        Setting::set('email_mailtrap_api_token', 'legacy-token-5555', 'email');

        $service = new EmailSettingsService();
        $profile = $service->getProfile();

        $this->assertSame('****4444', $profile['smtp']['passwordMasked']);
        $this->assertSame('****5555', $profile['mailtrap']['apiTokenMasked']);
        $this->assertTrue($profile['smtp']['configured']);
        $this->assertTrue($profile['mailtrap']['configured']);
    }

    public function test_persist_test_connection_snapshot_stores_last_probe_metadata(): void
    {
        $service = new EmailSettingsService();

        $user = new class {
            public int $id = 55;
            public string $uuid = 'f3ca8b20-1551-43f9-a9dd-2f039a412366';
            public string $email = 'tester@example.com';
        };

        $snapshot = $service->persistTestConnectionSnapshot([
            'provider' => 'smtp',
            'mode' => 'ephemeral',
            'connected' => false,
            'testedAt' => '2026-04-24T13:10:00+00:00',
            'details' => [
                'host' => 'smtp.example.com',
                'port' => 587,
                'timeout' => 10,
            ],
            'error' => [
                'code' => 'TIMEOUT',
                'message' => 'SMTP connection timed out.',
            ],
        ], $user);

        $this->assertSame('smtp', Setting::get('email_last_test_provider'));
        $this->assertSame('ephemeral', Setting::get('email_last_test_mode'));
        $this->assertSame(0, Setting::get('email_last_test_connected'));
        $this->assertSame('TIMEOUT', Setting::get('email_last_test_error_code'));
        $this->assertSame('tester@example.com', Setting::get('email_last_test_by_email'));
        $this->assertFalse($snapshot['connected']);
        $this->assertSame('TIMEOUT', $snapshot['error']['code']);
        $this->assertSame(55, $snapshot['updatedBy']['id']);
    }

    public function test_resolve_runtime_smtp_transport_uses_explicit_smtp_profile(): void
    {
        Setting::set('email_provider', 'smtp', 'email');
        Setting::set('email_smtp_host', 'smtp.example.com', 'email');
        Setting::set('email_smtp_port', '2525', 'email');
        Setting::set('email_smtp_encryption', 'tls', 'email');
        Setting::set('email_smtp_username', 'smtp-user', 'email');
        Setting::set('email_smtp_password', 'smtp-secret-9999', 'email');

        $service = new EmailSettingsService();
        $transport = $service->resolveRuntimeSmtpTransport();

        $this->assertTrue($transport['configured']);
        $this->assertSame('smtp', $transport['source']);
        $this->assertSame('smtp.example.com', $transport['host']);
        $this->assertSame(2525, $transport['port']);
        $this->assertSame('tls', $transport['encryption']);
        $this->assertSame('smtp-user', $transport['username']);
        $this->assertSame('smtp-secret-9999', $transport['password']);
    }

    public function test_resolve_runtime_smtp_transport_uses_mailtrap_token_defaults_when_provider_is_mailtrap(): void
    {
        Setting::set('email_provider', 'mailtrap', 'email');
        Setting::set('email_mailtrap_account_id', '2682142', 'email');
        Setting::set('email_mailtrap_api_token', 'mt-token-abc-123', 'email');

        $service = new EmailSettingsService();
        $transport = $service->resolveRuntimeSmtpTransport();

        $this->assertTrue($transport['configured']);
        $this->assertSame('mailtrap-token-default-smtp', $transport['source']);
        $this->assertSame('live.smtp.mailtrap.io', $transport['host']);
        $this->assertSame(587, $transport['port']);
        $this->assertSame('tls', $transport['encryption']);
        $this->assertSame('api', $transport['username']);
        $this->assertSame('mt-token-abc-123', $transport['password']);
    }
}
