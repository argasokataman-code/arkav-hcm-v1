<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('team', 100)->nullable();
            $table->string('designation', 150)->nullable();
            $table->string('phone', 50)->nullable();
            $table->text('address')->nullable();
            $table->text('bio')->nullable();
            $table->string('bank_name', 150)->nullable();
            $table->string('bank_account_no', 100)->nullable();
            $table->string('bank_ifsc_code', 100)->nullable();
            $table->string('bank_branch', 150)->nullable();
            $table->json('emergency_contacts')->nullable();
            $table->json('education_items')->nullable();
            $table->json('experience_items')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_profiles');
    }
};
