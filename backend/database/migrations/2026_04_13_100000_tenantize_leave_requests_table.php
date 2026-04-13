<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('leave_requests') || Schema::hasColumn('leave_requests', 'company_id')) {
            return;
        }

        Schema::table('leave_requests', function (Blueprint $table): void {
            $table->unsignedBigInteger('company_id')->nullable()->index();
        });

        if (! Schema::hasTable('companies')) {
            return;
        }

        $companyId = DB::table('companies')->where('code', 'default_company')->value('id');
        if (! $companyId) {
            $companyId = DB::table('companies')->orderBy('id')->value('id');
        }

        if ($companyId) {
            DB::table('leave_requests')->whereNull('company_id')->update(['company_id' => $companyId]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('leave_requests') || ! Schema::hasColumn('leave_requests', 'company_id')) {
            return;
        }

        Schema::table('leave_requests', function (Blueprint $table): void {
            $table->dropColumn('company_id');
        });
    }
};
