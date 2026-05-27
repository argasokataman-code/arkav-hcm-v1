<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Slice C — Checklist Item Management
 *
 * Standalone table for non-asset checklist items (replaces the non_asset_checklist
 * JSON blob on the parent record).  Each item can be created, updated, completed,
 * or soft-deleted independently.
 *
 * FK is ON DELETE RESTRICT (not CASCADE) so that a termination record with
 * checklist items cannot be hard-deleted while items still exist; this preserves
 * audit trail for finalized records (Anomaly #3 mitigation).
 *
 * SoftDeletes on items ensures historical completion evidence is never lost.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hcm_termination_checklist_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->unsignedBigInteger('termination_id');
            $table->foreign('termination_id')
                ->references('id')
                ->on('hcm_terminations')
                ->onDelete('restrict'); // Anomaly #3: no silent cascade delete

            $table->string('label', 255);
            $table->text('description')->nullable();
            $table->string('owner_name', 100)->nullable();
            $table->date('due_date')->nullable();
            $table->boolean('mandatory')->default(false);

            $table->enum('status', ['open', 'completed', 'skipped'])->default('open');
            $table->unsignedBigInteger('completed_by')->nullable(); // actor user_id — no FK (users table FK not reliable in this DB)
            $table->timestamp('completed_at')->nullable();
            $table->text('completion_evidence')->nullable();

            $table->timestamps();
            $table->softDeletes(); // Anomaly #3: soft delete — audit trail is never destroyed

            $table->index('termination_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_termination_checklist_items');
    }
};
