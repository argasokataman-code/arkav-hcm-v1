<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SettingsApiTest extends TestCase
{
    use RefreshDatabase;

    private function bearerToken(): string
    {
        $adminEmail = (string) config('hcm.admin_email', 'qa.login@example.com');

        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Settings Admin',
            'email' => $adminEmail,
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => $adminEmail,
            'password' => 'StrongPass1',
        ])->assertOk();

        $token = (string) $login->json('data.accessToken');
        $this->assertNotSame('', $token);

        return $token;
    }

    public function test_prefix_group_is_rejected_after_feature_removal(): void
    {
        $token = $this->bearerToken();

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->postJson('/v1/hcm/settings', [
            'group' => 'prefix',
            'settings' => [
                'employee' => 'EMPX-',
                'invoice' => 'INVX-',
            ],
        ])->assertStatus(422);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/v1/hcm/settings?group=prefix')
            ->assertStatus(422);

        $this->assertNull(Setting::get('prefix_employee'));
        $this->assertNull(Setting::get('prefix_invoice'));
    }

    public function test_can_upload_business_branding_file(): void
    {
        Storage::fake('public');
        $token = $this->bearerToken();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->post('/v1/hcm/settings/upload', [
            'group' => 'business',
            'field' => 'white_logo',
            'file' => UploadedFile::fake()->image('logo.png', 200, 80),
        ]);

        $response->assertOk()->assertJsonPath('success', true);

        $storedPath = (string) Setting::get('business_white_logo_path');
        $this->assertNotSame('', $storedPath);
        Storage::disk('public')->assertExists($storedPath);
    }

    public function test_settings_index_returns_centralized_full_payloads(): void
    {
        $token = $this->bearerToken();

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/v1/hcm/settings?group=business')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'business_company_name',
                    'business_email',
                    'business_phone',
                    'business_fax',
                    'business_website',
                    'business_address',
                    'business_country',
                    'business_state',
                    'business_city',
                    'business_postal_code',
                    'business_white_logo_path',
                    'business_dark_logo_path',
                    'business_white_mini_logo_path',
                    'business_dark_mini_logo_path',
                    'business_favicon_path',
                    'business_apple_icon_path',
                ],
            ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/v1/hcm/settings?group=localization')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'localization_language',
                    'localization_timezone',
                    'locale_timezone',
                    'localization_date_format',
                    'localization_time_format',
                    'localization_currency_code',
                    'localization_currency_symbol',
                    'localization_currency_position',
                    'localization_decimal_separator',
                    'localization_thousand_separator',
                    'localization_countries_restriction',
                    'localization_allowed_files',
                    'localization_max_file_size_mb',
                    'localization_language_switcher',
                    'localization_financial_year',
                    'localization_fy_start_month',
                ],
            ]);
    }

    public function test_localization_timezone_must_be_valid_timezone_identifier(): void
    {
        $token = $this->bearerToken();

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->postJson('/v1/hcm/settings', [
            'group' => 'localization',
            'settings' => [
                'timezone' => 'GMT+7',
            ],
        ])->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_ai_settings_masks_secret_on_load(): void
    {
        $token = $this->bearerToken();

        Setting::set('ai_openai_api_key', 'sk-real-value', 'ai');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/v1/hcm/settings?group=ai')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.ai_openai_api_key', '********');
    }

    public function test_ai_settings_keeps_existing_secret_when_mask_placeholder_is_submitted(): void
    {
        $token = $this->bearerToken();

        Setting::set('ai_openai_api_key', 'sk-existing', 'ai');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->postJson('/v1/hcm/settings', [
            'group' => 'ai',
            'settings' => [
                'openai_api_key' => '********',
            ],
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.openai_api_key', '********');

        $this->assertSame('sk-existing', Setting::get('ai_openai_api_key'));
    }
}
