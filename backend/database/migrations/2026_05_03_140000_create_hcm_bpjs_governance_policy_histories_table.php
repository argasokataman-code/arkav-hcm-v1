<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hcm_bpjs_governance_policy_histories')) {
            return;
        }

        Schema::create('hcm_bpjs_governance_policy_histories', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('policy_id')->nullable();
            $table->uuid('policy_uuid')->nullable();
            $table->string('action_type', 32); // created|updated|deleted
            $table->json('snapshot')->nullable();
            $table->unsignedBigInteger('changed_by_user_id')->nullable();
            $table->uuid('changed_by_user_uuid')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'created_at'], 'hcm_bpjs_policy_hist_company_created_idx');
            $table->index('policy_uuid', 'hcm_bpjs_policy_hist_policy_uuid_idx');
            $table->index('action_type', 'hcm_bpjs_policy_hist_action_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_bpjs_governance_policy_histories');
    }
};
