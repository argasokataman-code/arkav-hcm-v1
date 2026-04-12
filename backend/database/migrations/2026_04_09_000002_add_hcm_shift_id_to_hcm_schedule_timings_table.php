<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hcm_schedule_timings', function (Blueprint $table) {
            $table->foreignId('hcm_shift_id')->nullable()->after('user_id')->constrained('hcm_shifts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hcm_schedule_timings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hcm_shift_id');
        });
    }
};
