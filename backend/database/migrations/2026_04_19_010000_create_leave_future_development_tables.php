<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_approval_workflows', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->foreignId('leave_type_id')->nullable()->constrained('leave_types')->nullOnDelete();
            $table->string('name', 200);
            $table->decimal('min_days', 8, 2)->default(0);
            $table->decimal('max_days', 8, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->index(['leave_type_id', 'is_active', 'effective_from'], 'leave_approval_workflows_type_active_idx');
        });

        Schema::create('leave_approval_workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('leave_approval_workflows')->cascadeOnDelete();
            $table->unsignedTinyInteger('level');
            $table->string('approver_scope', 30); // manager|department_head|hr_admin|specific_user
            $table->foreignId('approver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('designation_id')->nullable()->constrained('designations')->nullOnDelete();
            $table->boolean('requires_all_approvers')->default(true);
            $table->unsignedSmallInteger('sla_hours')->nullable();
            $table->timestamps();

            $table->unique(['workflow_id', 'level'], 'leave_approval_workflow_steps_unique_level');
        });

        Schema::create('leave_blackout_dates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->foreignId('leave_type_id')->nullable()->constrained('leave_types')->nullOnDelete();
            $table->string('name', 200);
            $table->string('rule_type', 30)->default('hard_block'); // hard_block|warning_only|max_quota
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedSmallInteger('max_people_per_day')->nullable();
            $table->text('reason')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['leave_type_id', 'start_date', 'end_date'], 'leave_blackout_dates_type_date_idx');
        });

        Schema::create('leave_request_breakdowns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_request_id')->constrained('leave_requests')->cascadeOnDelete();
            $table->date('leave_date');
            $table->string('unit_type', 20)->default('full_day'); // full_day|half_day|hourly
            $table->string('session', 20)->nullable(); // morning|afternoon for half_day
            $table->unsignedSmallInteger('minutes')->nullable();
            $table->boolean('is_working_day')->default(true);
            $table->boolean('is_holiday')->default(false);
            $table->string('holiday_name', 200)->nullable();
            $table->decimal('deducted_days', 6, 2)->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['leave_request_id', 'leave_date', 'session'], 'leave_request_breakdowns_unique_row');
            $table->index(['leave_date', 'is_working_day', 'is_holiday'], 'leave_request_breakdowns_date_idx');
        });

        Schema::create('leave_request_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_request_id')->constrained('leave_requests')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('document_type', 40)->default('supporting_document'); // medical_certificate|marriage_certificate|other
            $table->string('file_name', 255);
            $table->string('file_path', 500);
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->boolean('is_required')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['leave_request_id', 'document_type'], 'leave_request_attachments_request_type_idx');
        });

        Schema::create('leave_request_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_request_id')->constrained('leave_requests')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 50); // created|updated|status_changed|attachment_added|deleted
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->json('changes')->nullable();
            $table->timestamps();

            $table->index(['leave_request_id', 'action', 'created_at'], 'leave_request_audits_request_action_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_request_audits');
        Schema::dropIfExists('leave_request_attachments');
        Schema::dropIfExists('leave_request_breakdowns');
        Schema::dropIfExists('leave_blackout_dates');
        Schema::dropIfExists('leave_approval_workflow_steps');
        Schema::dropIfExists('leave_approval_workflows');
    }
};
