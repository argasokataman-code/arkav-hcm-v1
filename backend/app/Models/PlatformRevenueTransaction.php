<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformRevenueTransaction extends Model
{
    use AssignsUuid, HasFactory;

    public const TYPE_SUBSCRIPTION = 'subscription';

    public const TYPE_PAYROLL_SERVICE = 'payroll_service';

    public const TYPE_ADDON_FEATURE = 'addon_feature';

    public const STATUS_POSTED = 'posted';

    public const STATUS_PENDING = 'pending';

    public const STATUS_CANCELLED = 'cancelled';

    public const CLEARING_UNCLEARED = 'uncleared';

    public const CLEARING_CLEARED = 'cleared';

    public const CLEARING_DISPUTED = 'disputed';

    public const CLEARING_REVERSED = 'reversed';

    protected $fillable = [
        'company_id',
        'source_event_type',
        'source_entity_type',
        'source_entity_id',
        'source_entity_uuid',
        'transaction_type',
        'amount',
        'tax_amount',
        'net_amount',
        'currency',
        'status',
        'clearing_status',
        'clearing_date',
        'dispute_reason',
        'idempotency_key',
        'occurred_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'source_entity_id' => 'integer',
            'amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'clearing_date' => 'date',
            'occurred_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeCleared(Builder $query): Builder
    {
        return $query->where('clearing_status', self::CLEARING_CLEARED);
    }

    public function scopeForMonth(Builder $query, string $month): Builder
    {
        $periodStart = $month.'-01';
        $periodEnd = date('Y-m-t', strtotime($periodStart));

        return $query->whereBetween('occurred_at', [$periodStart, $periodEnd]);
    }
}
