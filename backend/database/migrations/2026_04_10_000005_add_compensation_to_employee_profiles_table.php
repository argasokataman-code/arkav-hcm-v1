<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->decimal('base_salary', 15, 2)->default(0)->after('designation');
            $table->decimal('fixed_allowance', 15, 2)->default(0)->after('base_salary');
        });
    }

    public function down(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->dropColumn(['base_salary', 'fixed_allowance']);
        });
    }
};
