<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupEmailSettings extends Command
{
    protected $signature = 'email-settings:cleanup';

    protected $description = 'Remove email settings from database (SMTP config moved to .env only)';

    public function handle(): int
    {
        $this->info('🧹 Cleaning up email settings from database...');

        $settingsKeys = [
            'email_provider',
            'email_smtp_host',
            'email_smtp_port',
            'email_smtp_encryption',
            'email_smtp_username',
            'email_smtp_password',
            'email_mailtrap_account_id',
            'email_mailtrap_api_token',
            'email_from_address',
            'email_from_name',
        ];

        $deleted = DB::table('settings')
            ->whereIn('key', $settingsKeys)
            ->delete();

        $this->info("✅ Deleted {$deleted} email settings entries from database");
        $this->info('📝 SMTP configuration now managed via .env only');

        return self::SUCCESS;
    }
}
