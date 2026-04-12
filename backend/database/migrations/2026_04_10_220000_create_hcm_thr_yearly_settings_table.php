<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hcm_thr_yearly_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('calendar_year')->unique();
            $table->date('eid_date');
            $table->date('payment_date')->nullable();
            $table->date('calculation_cutoff_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_thr_yearly_settings');
    }
};
