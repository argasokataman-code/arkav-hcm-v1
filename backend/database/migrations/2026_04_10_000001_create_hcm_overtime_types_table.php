<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hcm_overtime_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->string('description', 500)->nullable();
            $table->decimal('payment_multiplier', 8, 2)->default(1.00);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        foreach ([
            ['weekday_ot', 'Weekday overtime', 'Lembur hari kerja', 1.50, 1],
            ['holiday_ot', 'Holiday overtime', 'Lembur hari libur / tanggal merah', 2.00, 2],
            ['night_ot', 'Night differential', 'Lembur/malus malam', 1.25, 3],
        ] as $r) {
            DB::table('hcm_overtime_types')->insert([
                'code' => $r[0],
                'name' => $r[1],
                'description' => $r[2],
                'payment_multiplier' => $r[3],
                'is_active' => true,
                'sort_order' => $r[4],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_overtime_types');
    }
};
