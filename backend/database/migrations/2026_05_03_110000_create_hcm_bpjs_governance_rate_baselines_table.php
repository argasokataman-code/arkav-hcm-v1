<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hcm_bpjs_governance_rate_baselines')) {
            return;
        }

        Schema::create('hcm_bpjs_governance_rate_baselines', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('program_code', 32);
            $table->string('contribution_party', 16);
            $table->decimal('min_rate', 7, 4);
            $table->decimal('max_rate', 7, 4);
            $table->string('wage_base', 64)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->uuid('updated_by_user_uuid')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'program_code', 'contribution_party'], 'hcm_bpjs_baseline_unique');
            $table->index('company_id', 'hcm_bpjs_baseline_company_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_bpjs_governance_rate_baselines');
    }
};
