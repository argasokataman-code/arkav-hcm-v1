<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')
            ->where('group', 'prefix')
            ->orWhere('key', 'like', 'prefix_%')
            ->delete();
    }

    public function down(): void
    {
        $defaults = [
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

        foreach ($defaults as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'group' => 'prefix', 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }
};
