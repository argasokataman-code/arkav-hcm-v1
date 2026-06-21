<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add company_id column if it doesn't already exist
        // Note: Not adding FK constraint due to MySQL unique index requirements
        if (Schema::hasTable('policies')) {
            Schema::table('policies', function (Blueprint $table): void {
                if (! Schema::hasColumn('policies', 'company_id')) {
                    $table->unsignedBigInteger('company_id')->nullable()->after('id');
                }
                if (! Schema::hasIndex('policies', 'policies_company_id_department_id_index')) {
                    $table->index(['company_id', 'department_id'], 'policies_company_id_department_id_index');
                }
            });
        }
    }

    public function down(): void
    {
        // Drop company_id column and index
        if (Schema::hasTable('policies')) {
            Schema::table('policies', function (Blueprint $table): void {
                if (Schema::hasColumn('policies', 'company_id')) {
                    $table->dropColumn('company_id');
                }
                if (Schema::hasIndex('policies', 'policies_company_id_department_id_index')) {
                    $table->dropIndex('policies_company_id_department_id_index');
                }
            });
        }
    }
};
