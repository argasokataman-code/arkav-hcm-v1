<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('geofences', function (Blueprint $table): void {
            $table->unique(['company_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('geofences', function (Blueprint $table): void {
            $table->dropUnique(['company_id', 'name']);
        });
    }
};
