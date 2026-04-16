<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Check if companies table exists before adding foreign keys
        if (!Schema::hasTable('companies')) {
            return;
        }

        Schema::table('policies', function (Blueprint $table): void {
            $table->foreignId('company_id')->nullable()->after('id')->constrained('companies')->nullOnDelete();
            $table->index(['company_id', 'department_id']);
        });
    }

    public function down(): void
    {
        // Check if companies table exists before dropping foreign keys
        if (!Schema::hasTable('companies')) {
            return;
        }

        Schema::table('policies', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('company_id');
            $table->dropIndex(['company_id', 'department_id']);
        });
    }
};

