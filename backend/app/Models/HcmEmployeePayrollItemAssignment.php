<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class HcmEmployeePayrollItemAssignment extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    protected $fillable = [
        'company_id',
        'user_id',
        'hcm_payroll_item_id',
        'amount',
        'is_active',
        'effective_start_date',
        'effective_end_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'user_id' => 'integer',
            'hcm_payroll_item_id' => 'integer',
            'amount' => 'decimal:2',
            'is_active' => 'boolean',
            'effective_start_date' => 'date',
            'effective_end_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payrollItem(): BelongsTo
    {
        return $this->belongsTo(HcmPayrollItem::class, 'hcm_payroll_item_id');
    }
}
