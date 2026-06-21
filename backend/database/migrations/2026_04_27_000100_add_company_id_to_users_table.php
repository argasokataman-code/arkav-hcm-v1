<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Check if column already exists
        if (! Schema::hasColumn('users', 'company_id')) {
            // Add company_id to users table for platform vs tenant users
            Schema::table('users', function (Blueprint $table): void {
                $table->unsignedBigInteger('company_id')->nullable()->after('id');
                $table->index('company_id', 'users_company_id_idx');
                // Note: Foreign key removed due to UUID primary key on companies
                // $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_company_id_idx');
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');
        });
    }
};
