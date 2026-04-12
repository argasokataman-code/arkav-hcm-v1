<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->date('holiday_date');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('leave_type', 100);
            $table->date('date_from');
            $table->date('date_to');
            $table->decimal('days', 5, 1)->default(1);
            $table->string('status', 20)->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('overtime_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('work_date');
            $table->unsignedSmallInteger('minutes');
            $table->string('project_name', 200)->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_requests');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('holidays');
    }
};
