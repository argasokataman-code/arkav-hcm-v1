<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Slice B — Workflow Audit History + Optimistic Lock Version
 *
 * workflow_history: ordered JSON log of every stage transition
 *   [{stage, action, actor_id, actor_name, actor_role, timestamp, note, previous_stage}]
 *
 * workflow_version: incremented on every stage update; clients must echo back
 *   the version they read so the controller can detect concurrent modification
 *   and return HTTP 409 Conflict instead of silently overwriting (Anomaly #2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hcm_terminations', function (Blueprint $table): void {
            $table->json('workflow_history')->nullable()->after('workflow_finalized_at');
            $table->unsignedInteger('workflow_version')->default(0)->after('workflow_history');
        });
    }

    public function down(): void
    {
        Schema::table('hcm_terminations', function (Blueprint $table): void {
            $table->dropColumn(['workflow_history', 'workflow_version']);
        });
    }
};
