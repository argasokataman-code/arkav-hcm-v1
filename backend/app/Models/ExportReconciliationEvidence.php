<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExportReconciliationEvidence extends Model
{
    use HasFactory;

    protected $table = 'export_reconciliation_evidences';

    public const FEATURE_PAYROLL_RUN = 'payroll_run';

    public const FEATURE_THR_BATCH = 'thr_batch';

    public const FEATURE_PKWT_COMPENSATION = 'pkwt_compensation';

    public const FEATURE_INVOICE = 'invoice';

    public const FEATURE_PAYMENT = 'payment';

    public const ACTION_FINALIZE = 'finalize';

    public const ACTION_DISBURSE = 'disburse';

    public const ACTION_POST_PAYROLL = 'post_payroll';

    public const ACTION_MARK_PAID = 'mark_paid';

    public const ACTION_VERIFY = 'verify';

    protected $fillable = [
        'company_id',
        'feature_key',
        'action_key',
        'scope_ref',
        'exported_by_user_id',
        'exported_at',
        'file_format',
        'file_path',
        'row_count',
        'filter_payload',
        'dataset_checksum',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'exported_by_user_id' => 'integer',
            'row_count' => 'integer',
            'filter_payload' => 'array',
            'exported_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function scopeForContext(
        Builder $query,
        ?int $companyId,
        string $featureKey,
        string $actionKey,
        string $scopeRef
    ): Builder {
        return $query
            ->where('company_id', $companyId)
            ->where('feature_key', $featureKey)
            ->where('action_key', $actionKey)
            ->where('scope_ref', $scopeRef);
    }

    public static function latestByScope(
        ?int $companyId,
        string $featureKey,
        string $actionKey,
        string $scopeRef
    ): ?self {
        return self::query()
            ->forContext($companyId, $featureKey, $actionKey, $scopeRef)
            ->orderByDesc('exported_at')
            ->orderByDesc('id')
            ->first();
    }

    public function exportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'exported_by_user_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function isExpired(?CarbonInterface $at = null): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        $compareAt = $at ?? now();

        return $this->expires_at->lt($compareAt);
    }
}