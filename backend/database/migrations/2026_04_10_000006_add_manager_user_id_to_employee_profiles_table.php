<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table): void {
            $table->foreignId('manager_user_id')
                ->nullable()
                ->after('user_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->index(['manager_user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('manager_user_id');
        });
    }
};
