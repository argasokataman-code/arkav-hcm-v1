<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hcm_leave_type_settings', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 150);
            $table->boolean('is_enabled')->default(true);
            $table->decimal('days', 8, 2)->nullable();
            $table->boolean('carry_forward')->default(false);
            $table->unsignedSmallInteger('max_carry_days')->nullable();
            $table->boolean('earned_leave')->default(false);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('hcm_leave_custom_policies', function (Blueprint $table) {
            $table->id();
            $table->string('leave_type_code', 64)->index();
            $table->string('name', 200);
            $table->decimal('days', 8, 2);
            $table->json('assignee_user_ids')->nullable();
            $table->timestamps();
        });

        $now = now();
        $rows = [
            ['annual_leave', 'Annual Leave', true, 12, true, 5, true, 1],
            ['sick_leave', 'Sick Leave', false, 0, false, null, false, 2],
            ['hospitalisation', 'Hospitalisation', true, 0, false, null, false, 3],
            ['maternity', 'Maternity', true, 0, false, null, false, 4],
            ['paternity', 'Paternity', false, 0, false, null, false, 5],
            ['lop', 'LOP', false, 0, false, null, false, 6],
        ];
        foreach ($rows as $r) {
            DB::table('hcm_leave_type_settings')->insert([
                'code' => $r[0],
                'name' => $r[1],
                'is_enabled' => $r[2],
                'days' => $r[3],
                'carry_forward' => $r[4],
                'max_carry_days' => $r[5],
                'earned_leave' => $r[6],
                'sort_order' => $r[7],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_leave_custom_policies');
        Schema::dropIfExists('hcm_leave_type_settings');
    }
};
