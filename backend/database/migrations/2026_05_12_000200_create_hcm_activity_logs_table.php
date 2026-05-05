<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hcm_activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('entity_type', 100)->index();   // e.g. employee, payroll_run, leave_request
            $table->uuid('entity_uuid')->index();
            $table->string('action', 100)->index();        // created, updated, deleted, exported, approved, rejected
            $table->uuid('performed_by_uuid')->nullable()->index(); // user UUID who performed the action
            $table->string('performed_by_email', 255)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->json('changed_fields')->nullable();    // array of changed field names (not values)
            $table->json('meta')->nullable();              // extra context (e.g. export_format, payroll_period)
            $table->timestamp('occurred_at')->useCurrent()->index();

            $table->index(['company_id', 'entity_type', 'occurred_at']);
            $table->index(['company_id', 'performed_by_uuid', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_activity_logs');
    }
};
