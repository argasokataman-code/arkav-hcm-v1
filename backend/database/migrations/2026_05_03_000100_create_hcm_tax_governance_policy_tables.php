<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hcm_tax_governance_policies')) {
            Schema::create('hcm_tax_governance_policies', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('company_id');
                $table->uuid('company_uuid')->nullable();
                $table->string('policy_code', 100);
                $table->string('name', 255);
                $table->string('status', 32)->default('draft');
                $table->date('effective_start_date');
                $table->date('effective_end_date')->nullable();
                $table->json('rules');
                $table->json('rate_schedules');
                $table->unsignedInteger('version')->default(1);
                $table->unsignedBigInteger('created_by_user_id')->nullable();
                $table->uuid('created_by_user_uuid')->nullable();
                $table->unsignedBigInteger('submitted_by_user_id')->nullable();
                $table->uuid('submitted_by_user_uuid')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->unsignedBigInteger('approved_by_user_id')->nullable();
                $table->uuid('approved_by_user_uuid')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->unsignedBigInteger('published_by_user_id')->nullable();
                $table->uuid('published_by_user_uuid')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->text('last_note')->nullable();
                $table->timestamps();

                $table->index('company_id', 'hcm_tax_policy_company_id_idx');
                $table->index('company_uuid', 'hcm_tax_policy_company_uuid_idx');
                $table->index(['company_id', 'status'], 'hcm_tax_policy_company_status_idx');
                $table->index(['company_id', 'policy_code'], 'hcm_tax_policy_company_code_idx');
                $table->unique(['company_id', 'policy_code', 'version'], 'hcm_tax_policy_company_code_version_uq');

                $table->foreign('company_uuid', 'hcm_tax_policies_company_uuid_fk')->references('uuid')->on('companies')->nullOnDelete();
                $table->foreign('created_by_user_uuid', 'hcm_tax_policies_created_by_uuid_fk')->references('uuid')->on('users')->nullOnDelete();
                $table->foreign('submitted_by_user_uuid', 'hcm_tax_policies_submitted_by_uuid_fk')->references('uuid')->on('users')->nullOnDelete();
                $table->foreign('approved_by_user_uuid', 'hcm_tax_policies_approved_by_uuid_fk')->references('uuid')->on('users')->nullOnDelete();
                $table->foreign('published_by_user_uuid', 'hcm_tax_policies_published_by_uuid_fk')->references('uuid')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('hcm_tax_governance_policy_events')) {
            Schema::create('hcm_tax_governance_policy_events', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('company_id');
                $table->uuid('company_uuid')->nullable();
                $table->unsignedBigInteger('hcm_tax_governance_policy_id');
                $table->uuid('hcm_tax_governance_policy_uuid')->nullable();
                $table->string('event_type', 64);
                $table->unsignedBigInteger('actor_user_id')->nullable();
                $table->uuid('actor_user_uuid')->nullable();
                $table->json('before_state')->nullable();
                $table->json('after_state')->nullable();
                $table->text('note')->nullable();
                $table->timestamps();

                $table->index('company_id', 'hcm_tax_policy_event_company_id_idx');
                $table->index('company_uuid', 'hcm_tax_policy_event_company_uuid_idx');
                $table->index('hcm_tax_governance_policy_id', 'hcm_tax_policy_event_policy_id_idx');
                $table->index('hcm_tax_governance_policy_uuid', 'hcm_tax_policy_event_policy_uuid_idx');
                $table->index(['hcm_tax_governance_policy_id', 'event_type'], 'hcm_tax_policy_event_policy_type_idx');
                $table->index(['company_id', 'created_at'], 'hcm_tax_policy_event_company_created_idx');

                $table->foreign('company_uuid', 'hcm_tax_policy_events_company_uuid_fk')->references('uuid')->on('companies')->nullOnDelete();
                $table->foreign('hcm_tax_governance_policy_uuid', 'hcm_tax_policy_events_policy_uuid_fk')->references('uuid')->on('hcm_tax_governance_policies')->nullOnDelete();
                $table->foreign('actor_user_uuid', 'hcm_tax_policy_events_actor_uuid_fk')->references('uuid')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_tax_governance_policy_events');
        Schema::dropIfExists('hcm_tax_governance_policies');
    }
};
