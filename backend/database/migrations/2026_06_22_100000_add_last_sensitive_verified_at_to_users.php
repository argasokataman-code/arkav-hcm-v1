<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * UU PDP M8: Add last_sensitive_verified_at to track when user last
     * re-authenticated for sensitive operations (payroll, erasure, breach).
     */
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'last_sensitive_verified_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->timestamp('last_sensitive_verified_at')->nullable()->after('email_verified_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'last_sensitive_verified_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('last_sensitive_verified_at');
            });
        }
    }
};
