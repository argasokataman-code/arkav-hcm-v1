<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HcmThrBatchLine extends Model
{
    use AssignsUuid;

    protected $fillable = [
        'thr_slip_public_no',
        'hcm_thr_batch_id',
        'user_id',
        'full_name',
        'employee_no',
        'join_date_used',
        'base_salary',
        'fixed_allowance',
        'reference_wage',
        'months_of_service',
        'multiplier',
        'thr_gross',
        'row_status',
        'eligible',
        'payment_status',
        'payment_failure_reason',
        'payment_gateway_ref',
        'paid_at',
        'slip_storage_path',
        'slip_generated_at',
        'slip_notify_sent_at',
        'last_disbursement_id',
    ];

    public const PAYMENT_UNPAID = 'unpaid';

    public const PAYMENT_PENDING = 'pending';

    public const PAYMENT_PAID = 'paid';

    public const PAYMENT_FAILED = 'failed';

    protected function casts(): array
    {
        return [
            'join_date_used' => 'date',
            'base_salary' => 'decimal:2',
            'fixed_allowance' => 'decimal:2',
            'reference_wage' => 'decimal:2',
            'months_of_service' => 'integer',
            'multiplier' => 'decimal:6',
            'thr_gross' => 'decimal:2',
            'eligible' => 'boolean',
            'paid_at' => 'datetime',
            'slip_generated_at' => 'datetime',
            'slip_notify_sent_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(HcmThrBatch::class, 'hcm_thr_batch_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lastDisbursement(): BelongsTo
    {
        return $this->belongsTo(HcmThrDisbursement::class, 'last_disbursement_id');
    }
}
