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
        Schema::create('payroll_settings_audit_log', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->bigInteger('company_id')->index();
            $table->bigInteger('user_id')->nullable();
            $table->string('action')->default('update')->comment('update, restore, etc');
            $table->string('setting_key')->index();
            $table->longText('old_value')->nullable()->comment('Previous value or null');
            $table->longText('new_value')->nullable()->comment('New value or null');
            $table->timestamp('changed_at')->useCurrent();
            $table->string('ip_address')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'changed_at']);
            $table->index(['setting_key', 'company_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_settings_audit_log');
    }
};
