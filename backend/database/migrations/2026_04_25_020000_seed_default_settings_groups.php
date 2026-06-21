<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $now = now();

        $prefixDefaults = [
            'prefix_employee' => 'EMP-',
            'prefix_clients' => 'CLI-',
            'prefix_invoice' => 'INV-',
            'prefix_tickets' => 'TIC-',
            'prefix_candidate' => 'CND-',
            'prefix_job' => 'JOB-',
            'prefix_referral' => 'REF-',
            'prefix_contract' => 'CNT-',
            'prefix_department' => 'DPT-',
            'prefix_leave' => 'LVE-',
            'prefix_assets' => 'AST-',
        ];

        $businessDefaults = [
            'business_company_name' => 'Arkav - Human Capital Management',
            'business_email' => null,
            'business_phone' => null,
            'business_fax' => null,
            'business_website' => null,
            'business_address' => null,
            'business_country' => null,
            'business_state' => null,
            'business_city' => null,
            'business_postal_code' => null,
            'business_white_logo_path' => null,
            'business_dark_logo_path' => null,
            'business_white_mini_logo_path' => null,
            'business_dark_mini_logo_path' => null,
            'business_favicon_path' => null,
            'business_apple_icon_path' => null,
        ];

        $localizationDefaults = [
            'localization_language' => 'en',
            'localization_timezone' => 'UTC',
            'locale_timezone' => 'UTC',
            'localization_date_format' => 'd M Y',
            'localization_time_format' => '24',
            'localization_language_switcher' => '0',
            'localization_financial_year' => null,
            'localization_fy_start_month' => null,
            'localization_currency_code' => 'IDR',
            'localization_currency_symbol' => 'Rp',
            'localization_currency_position' => 'prefix',
            'localization_decimal_separator' => ',',
            'localization_thousand_separator' => '.',
            'localization_countries_restriction' => 'allow_all',
            'localization_allowed_files' => 'jpg,jpeg,png',
            'localization_max_file_size_mb' => '5000',
        ];

        $this->insertIfMissing($prefixDefaults, 'prefix', $now);
        $this->insertIfMissing($businessDefaults, 'business', $now);
        $this->insertIfMissing($localizationDefaults, 'localization', $now);
    }

    public function down(): void
    {
        // Intentionally no-op to avoid deleting user-customized settings.
    }

    private function insertIfMissing(array $values, string $group, $now): void
    {
        foreach ($values as $key => $value) {
            $exists = DB::table('settings')->where('key', $key)->exists();
            if ($exists) {
                continue;
            }

            DB::table('settings')->insert([
                'key' => $key,
                'value' => $value,
                'group' => $group,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};
