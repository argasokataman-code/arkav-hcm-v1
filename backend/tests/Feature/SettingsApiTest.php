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

    public function test_can_save_and_load_prefix_settings(): void
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
        ])->assertOk()->assertJsonPath('success', true);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/v1/hcm/settings?group=prefix')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.prefix_employee', 'EMPX-')
            ->assertJsonPath('data.prefix_invoice', 'INVX-');

        $this->assertSame('EMPX-', Setting::get('prefix_employee'));
        $this->assertSame('INVX-', Setting::get('prefix_invoice'));
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
        ])->getJson('/v1/hcm/settings?group=prefix')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'prefix_employee',
                    'prefix_clients',
                    'prefix_invoice',
                    'prefix_tickets',
                    'prefix_candidate',
                    'prefix_job',
                    'prefix_referral',
                    'prefix_contract',
                    'prefix_department',
                    'prefix_leave',
                    'prefix_assets',
                ],
            ]);

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
}
