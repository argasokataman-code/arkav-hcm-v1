<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->foreignId('department_id')
                ->nullable()
                ->after('user_id')
                ->constrained('departments')
                ->nullOnDelete();
            $table->foreignId('designation_id')
                ->nullable()
                ->after('department_id')
                ->constrained('designations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('designation_id');
            $table->dropConstrainedForeignId('department_id');
        });
    }
};
