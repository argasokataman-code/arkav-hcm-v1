<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class EmployeeCompensation extends Model
{
    protected $table = 'employee_compensations';

    protected static function booted(): void
    {
        static::creating(function (self $record): void {
            if (empty($record->uuid)) {
                $record->uuid = (string) Str::uuid();
            }
        });
    }

    protected $fillable = [
        'employee_id',
        'salary_type',
        'base_salary',
        'fixed_allowance',
        'currency',
        'effective_date',
        'end_date',
        'notes',
    ];

    protected $casts = [
        'base_salary' => 'decimal:2',
        'fixed_allowance' => 'decimal:2',
        'effective_date' => 'date',
        'end_date' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class, 'employee_id');
    }
}
