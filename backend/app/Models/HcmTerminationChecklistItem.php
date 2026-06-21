<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * HcmTerminationChecklistItem
 *
 * Represents a single checklist item linked to a termination record.
 * Replaces the flat non_asset_checklist JSON blob with a proper relational
 * structure that supports per-item completion tracking and audit trail.
 *
 * SoftDeletes: ensures historical evidence is never permanently destroyed
 * (Anomaly #3 — no hard delete on audit-critical records).
 *
 * FK to hcm_terminations is ON DELETE RESTRICT (set in migration) so a
 * termination record that still has checklist items cannot be hard-deleted.
 */
class HcmTerminationChecklistItem extends Model
{
    use AssignsUuid, SoftDeletes;

    protected $table = 'hcm_termination_checklist_items';

    protected $fillable = [
        'uuid',
        'termination_id',
        'label',
        'description',
        'owner_name',
        'due_date',
        'mandatory',
        'status',
        'completed_by',
        'completed_at',
        'completion_evidence',
    ];

    protected function casts(): array
    {
        return [
            'termination_id' => 'integer',
            'mandatory' => 'boolean',
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'completed_by' => 'integer',
        ];
    }

    /**
     * Statuses allowed for this checklist item.
     */
    public const STATUSES = ['open', 'completed', 'skipped'];

    // =========================================================================
    // Relationships
    // =========================================================================

    public function termination(): BelongsTo
    {
        return $this->belongsTo(HcmTermination::class, 'termination_id');
    }

    public function completedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
