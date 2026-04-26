<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('employee_profiles', 'team_id')) {
            Schema::table('employee_profiles', function (Blueprint $table) {
                $table->foreignId('team_id')
                    ->nullable()
                    ->after('team')
                    ->constrained('teams', 'id')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_id');
        });
    }
};
