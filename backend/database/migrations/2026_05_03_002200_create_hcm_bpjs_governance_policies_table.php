<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hcm_bpjs_governance_policies')) {
            return;
        }

        Schema::create('hcm_bpjs_governance_policies', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('company_id');
            $table->uuid('company_uuid')->nullable();
            $table->string('program_code', 32);
            $table->string('contribution_party', 16);
            $table->decimal('rate_percent', 7, 4);
            $table->string('wage_base', 64)->nullable();
            $table->date('effective_start_date');
            $table->date('effective_end_date')->nullable();
            $table->text('legal_basis')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->uuid('created_by_user_uuid')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->uuid('updated_by_user_uuid')->nullable();
            $table->timestamps();

            $table->index('company_id', 'hcm_bpjs_policy_company_id_idx');
            $table->index('company_uuid', 'hcm_bpjs_policy_company_uuid_idx');
            $table->index(['company_id', 'program_code', 'contribution_party'], 'hcm_bpjs_policy_program_party_idx');
            $table->index(['company_id', 'is_active', 'effective_start_date'], 'hcm_bpjs_policy_active_effective_idx');
            $table->unique(['company_id', 'program_code', 'contribution_party', 'effective_start_date'], 'hcm_bpjs_policy_version_unique');

            $table->foreign('company_uuid', 'hcm_bpjs_policy_company_uuid_fk')->references('uuid')->on('companies')->nullOnDelete();
            $table->foreign('created_by_user_uuid', 'hcm_bpjs_policy_created_by_uuid_fk')->references('uuid')->on('users')->nullOnDelete();
            $table->foreign('updated_by_user_uuid', 'hcm_bpjs_policy_updated_by_uuid_fk')->references('uuid')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_bpjs_governance_policies');
    }
};
