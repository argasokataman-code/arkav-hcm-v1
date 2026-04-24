<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Seed baseline keys for email settings persistence in the shared settings table.
     */
    public function up(): void
    {
        $now = now();

        $defaults = [
            'email_provider' => 'mailtrap',
            'email_from_address' => null,
            'email_from_name' => config('mail.from.name'),
            'email_smtp_host' => null,
            'email_smtp_port' => '587',
            'email_smtp_encryption' => 'tls',
            'email_smtp_username' => null,
            'email_smtp_password' => null,
            'email_mailtrap_account_id' => null,
            'email_mailtrap_api_token' => null,
        ];

        foreach ($defaults as $key => $value) {
            $exists = DB::table('settings')->where('key', $key)->exists();
            if ($exists) {
                continue;
            }

            DB::table('settings')->insert([
                'key' => $key,
                'value' => $value,
                'group' => 'email',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')
            ->whereIn('key', [
                'email_provider',
                'email_from_address',
                'email_from_name',
                'email_smtp_host',
                'email_smtp_port',
                'email_smtp_encryption',
                'email_smtp_username',
                'email_smtp_password',
                'email_mailtrap_account_id',
                'email_mailtrap_api_token',
            ])
            ->delete();
    }
};
