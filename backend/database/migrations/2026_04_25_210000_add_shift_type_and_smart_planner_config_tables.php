<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hcm_shifts', function (Blueprint $table) {
            if (! Schema::hasColumn('hcm_shifts', 'shift_type')) {
                $table->string('shift_type', 20)->nullable()->after('end_time');
            }
        });

        Schema::create('hcm_smart_planner_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->json('default_rules')->nullable();
            $table->json('forbidden_transitions')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamps();

            $table->unique('company_id', 'hcm_smart_planner_settings_company_unique');
            $table->index('company_id');
        });

        Schema::create('hcm_schedule_rosters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->date('work_date');
            $table->unsignedBigInteger('hcm_shift_id')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->boolean('cross_day')->default(false);
            $table->string('roster_status', 20)->default('working');
            $table->string('source', 20)->default('planner');
            $table->unsignedBigInteger('published_by_user_id')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'user_id', 'work_date'], 'hcm_schedule_rosters_company_user_date_unique');
            $table->index(['company_id', 'work_date'], 'hcm_schedule_rosters_company_work_date_idx');
            $table->index('user_id');
            $table->index('hcm_shift_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_schedule_rosters');
        Schema::dropIfExists('hcm_smart_planner_settings');

        Schema::table('hcm_shifts', function (Blueprint $table) {
            if (Schema::hasColumn('hcm_shifts', 'shift_type')) {
                $table->dropColumn('shift_type');
            }
        });
    }
};
