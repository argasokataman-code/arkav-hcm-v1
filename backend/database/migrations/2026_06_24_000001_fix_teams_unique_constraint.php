<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('teams')) {
            return;
        }

        Schema::table('teams', function (Blueprint $table): void {
            $table->index('department_id', 'teams_department_id_index');
            $table->dropUnique('teams_department_id_name_unique');
            $table->unique(['company_id', 'name'], 'teams_company_id_name_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('teams')) {
            return;
        }

        Schema::table('teams', function (Blueprint $table): void {
            $table->dropUnique('teams_company_id_name_unique');
            $table->unique(['department_id', 'name'], 'teams_department_id_name_unique');
            $table->dropIndex('teams_department_id_index');
        });
    }
};
