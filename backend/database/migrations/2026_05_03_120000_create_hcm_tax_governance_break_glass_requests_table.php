<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hcm_tax_governance_break_glass_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('target_company_id');
            $table->uuid('target_company_uuid')->nullable();
            $table->unsignedBigInteger('requested_by_user_id');
            $table->uuid('requested_by_user_uuid')->nullable();
            $table->unsignedBigInteger('approved_by_user_id')->nullable();
            $table->uuid('approved_by_user_uuid')->nullable();
            $table->string('reason_code', 100);
            $table->text('reason');
            $table->text('approval_note')->nullable();
            $table->string('status', 32)->default('requested');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index('target_company_uuid', 'hcm_tax_bg_req_target_uuid_idx');
            $table->index('requested_by_user_uuid', 'hcm_tax_bg_req_requested_uuid_idx');
            $table->index('approved_by_user_uuid', 'hcm_tax_bg_req_approved_uuid_idx');
            $table->index(['target_company_id', 'status'], 'hcm_tax_bg_req_target_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_tax_governance_break_glass_requests');
    }
};
