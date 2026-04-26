<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('hcm_payroll_runs', 'meta')) {
            return;
        }

        Schema::table('hcm_payroll_runs', function (Blueprint $table): void {
            $table->json('meta')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('hcm_payroll_runs', 'meta')) {
            return;
        }

        Schema::table('hcm_payroll_runs', function (Blueprint $table): void {
            $table->dropColumn('meta');
        });
    }
};