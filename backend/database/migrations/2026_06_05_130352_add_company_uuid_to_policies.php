<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('policies', function (Blueprint $table) {
            // Add company_uuid for multi-tenant scoping
            if (!Schema::hasColumn('policies', 'company_uuid')) {
                $table->char('company_uuid', 36)->nullable();
            }
            if (!Schema::hasIndex('policies', 'policies_company_uuid_index')) {
                $table->index('company_uuid');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('policies', function (Blueprint $table) {
            if (Schema::hasIndex('policies', 'policies_company_uuid_index')) {
                $table->dropIndex('policies_company_uuid_index');
            }
            if (Schema::hasColumn('policies', 'company_uuid')) {
                $table->dropColumn('company_uuid');
            }
        });
    }
};
