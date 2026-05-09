<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payroll_settings_snapshots', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->bigInteger('company_id')->index();
            $table->unsignedInteger('snapshot_version')->default(1);
            $table->bigInteger('user_id')->nullable();
            $table->json('settings_data')->comment('Complete payroll settings snapshot as JSON');
            $table->string('change_reason')->nullable();
            $table->timestamp('changed_at')->useCurrent();
            $table->timestamps();
            $table->index(['company_id', 'snapshot_version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_settings_snapshots');
    }
};
