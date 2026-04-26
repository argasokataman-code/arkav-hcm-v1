<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('teams') || Schema::hasColumn('teams', 'team_lead_id')) {
            return;
        }

        Schema::table('teams', function (Blueprint $table): void {
            $table->unsignedBigInteger('team_lead_id')
                ->nullable()
                ->after('department_id');
            $table->index('team_lead_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('teams') || ! Schema::hasColumn('teams', 'team_lead_id')) {
            return;
        }

        Schema::table('teams', function (Blueprint $table): void {
            $table->dropIndex(['team_lead_id']);
            $table->dropColumn('team_lead_id');
        });
    }
};
