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
        // Projection table for cross-tenant observability
        if (!Schema::hasTable('hcm_tax_governance_projections')) {
            Schema::create('hcm_tax_governance_projections', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->unsignedBigInteger('company_id');
                $table->uuid('policy_uuid')->nullable();
                $table->enum('status', ['draft', 'submitted', 'approved', 'published', 'superseded', 'void']);
                $table->integer('version')->default(0);
                $table->date('effective_date')->nullable();
                $table->date('end_date')->nullable();
                $table->unsignedBigInteger('last_actor_user_id')->nullable();
                $table->enum('last_actor_action', ['created', 'submitted', 'approved', 'published', 'superseded', 'voided'])->nullable();
                $table->timestamp('last_actor_timestamp')->nullable();
                $table->integer('policy_complexity_score')->default(0);
                $table->json('anomaly_flags')->nullable();
                $table->enum('tenant_risk_level', ['green', 'yellow', 'red'])->default('green');
                $table->timestamps();

                // Indexes with short names to avoid MySQL 64-char limit
                $table->index(['company_id'], 'idx_co_id');
                $table->index(['policy_uuid'], 'idx_policy_id');
                $table->index(['status'], 'idx_status');
                $table->index(['tenant_risk_level'], 'idx_risk_level');
                $table->index(['company_id', 'status'], 'idx_co_status');
                $table->index(['company_id', 'created_at'], 'idx_co_created');
            });
        }

        // Anomaly registry for detected issues
        if (!Schema::hasTable('hcm_tax_governance_anomalies')) {
            Schema::create('hcm_tax_governance_anomalies', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->unsignedBigInteger('company_id');
                $table->enum('anomaly_type', [
                    'MISSING_TAX_PROFILE',
                    'POLICY_DRAFT_STALE',
                    'POLICY_SUPERSEDED_ACTIVE',
                    'POLICY_VERSION_CONFLICT',
                    'PUBLISH_FAILURE',
                    'DRIFT_DETECTED',
                ]);
                $table->enum('severity', ['info', 'warning', 'critical']);
                $table->uuid('affected_policy_id')->nullable();
                $table->unsignedBigInteger('affected_employee_id')->nullable();
                $table->text('description');
                $table->json('evidence_snapshot')->nullable();
                $table->timestamp('detected_at');
                $table->timestamp('resolved_at')->nullable();
                $table->text('resolution_note')->nullable();
                $table->timestamps();

                // Indexes with short names to avoid MySQL 64-char limit
                $table->index(['company_id'], 'idx_anom_co_id');
                $table->index(['anomaly_type'], 'idx_anom_type');
                $table->index(['severity'], 'idx_anom_severity');
                $table->index(['affected_policy_id'], 'idx_anom_policy');
                $table->index(['detected_at'], 'idx_anom_detected');
                $table->index(['resolved_at'], 'idx_anom_resolved');
                $table->index(['company_id', 'severity'], 'idx_anom_co_sev');
                $table->index(['company_id', 'anomaly_type'], 'idx_anom_co_type');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hcm_tax_governance_anomalies');
        Schema::dropIfExists('hcm_tax_governance_projections');
    }
};
