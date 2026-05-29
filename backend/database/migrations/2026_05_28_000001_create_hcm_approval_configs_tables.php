<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guard against partial state from a previously failed run attempt.
        Schema::dropIfExists('hcm_approval_config_approvers');
        Schema::dropIfExists('hcm_approval_configs');

        Schema::create('hcm_approval_configs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('company_id');
            $table->uuid('company_uuid')->nullable();
            $table->string('module', 50)->comment('leave | expense | offer | overtime');
            $table->enum('approval_mode', ['sequence', 'simultaneous'])->default('simultaneous');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'module'], 'hcm_approval_configs_company_module_unique');
            $table->index('company_id', 'hcm_approval_configs_company_id_idx');
            $table->index('company_uuid', 'hcm_approval_configs_company_uuid_idx');
            $table->foreign('company_uuid', 'hcm_approval_configs_company_uuid_fk')
                ->references('uuid')->on('companies')->nullOnDelete();
        });

        Schema::create('hcm_approval_config_approvers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('hcm_approval_config_id');
            $table->unsignedBigInteger('company_id');
            $table->uuid('company_uuid')->nullable();
            $table->unsignedBigInteger('approver_user_id');
            $table->string('approver_user_uuid', 36)->nullable();
            $table->unsignedTinyInteger('sequence_order')->default(1)->comment('Used for sequence mode: level 1 approves before level 2');
            $table->timestamps();

            $table->index(['hcm_approval_config_id', 'sequence_order'], 'hcm_acapprovers_config_order_idx');
            $table->index('company_id', 'hcm_acapprovers_company_id_idx');
            $table->index('company_uuid', 'hcm_acapprovers_company_uuid_idx');
            $table->index('approver_user_id', 'hcm_acapprovers_approver_user_id_idx');
            $table->index('approver_user_uuid', 'hcm_acapprovers_approver_user_uuid_idx');
            $table->foreign('hcm_approval_config_id')->references('id')->on('hcm_approval_configs')->cascadeOnDelete();
            $table->foreign('company_uuid', 'hcm_acapprovers_company_uuid_fk')
                ->references('uuid')->on('companies')->nullOnDelete();
            $table->foreign('approver_user_uuid', 'hcm_acapprovers_approver_user_uuid_fk')
                ->references('uuid')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_approval_config_approvers');
        Schema::dropIfExists('hcm_approval_configs');
    }
};
