<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hcm_salary_components', function (Blueprint $table) {
            $table->dropColumn(['description', 'legal_basis', 'legal_notes', 'default_percent', 'percent_basis']);
        });
    }

    public function down(): void
    {
        Schema::table('hcm_salary_components', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
            $table->string('legal_basis', 500)->nullable()->after('category');
            $table->text('legal_notes')->nullable()->after('legal_basis');
            $table->decimal('default_percent', 7, 4)->nullable()->after('is_active');
            $table->string('percent_basis', 64)->nullable()->after('default_percent');
        });
    }
};
