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
                $table->string('policy_code', 100);
                $table->string('name', 255);
                $table->string('status', 32)->default('draft');
                $table->date('effective_start_date');
                $table->date('effective_end_date')->nullable();
                $table->json('rules');
                $table->json('rate_schedules');
                $table->unsignedInteger('version')->default(1);
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('submitted_at')->nullable();
                $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->foreignId('published_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('published_at')->nullable();
                $table->text('last_note')->nullable();
                $table->timestamps();

                $table->index('company_id', 'hcm_tax_policy_company_id_idx');
                $table->index(['company_id', 'status'], 'hcm_tax_policy_company_status_idx');
                $table->index(['company_id', 'policy_code'], 'hcm_tax_policy_company_code_idx');
                $table->unique(['company_id', 'policy_code', 'version'], 'hcm_tax_policy_company_code_version_uq');
            });
        }

        if (! Schema::hasTable('hcm_tax_governance_policy_events')) {
            Schema::create('hcm_tax_governance_policy_events', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('hcm_tax_governance_policy_id');
                $table->string('event_type', 64);
                $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->json('before_state')->nullable();
                $table->json('after_state')->nullable();
                $table->text('note')->nullable();
                $table->timestamps();

                $table->index('company_id', 'hcm_tax_policy_event_company_id_idx');
                $table->index('hcm_tax_governance_policy_id', 'hcm_tax_policy_event_policy_id_idx');
                $table->index(['hcm_tax_governance_policy_id', 'event_type'], 'hcm_tax_policy_event_policy_type_idx');
                $table->index(['company_id', 'created_at'], 'hcm_tax_policy_event_company_created_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_tax_governance_policy_events');
        Schema::dropIfExists('hcm_tax_governance_policies');
    }
};
