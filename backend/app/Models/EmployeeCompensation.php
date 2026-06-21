<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeCompensation extends Model
{
    use AssignsUuid;

    protected $table = 'employee_compensations';

    protected $fillable = [
        'employee_id',
        'salary_type',
        'base_salary',
        'currency',
        'effective_date',
        'end_date',
        'notes',
    ];

    protected $casts = [
        'base_salary' => 'decimal:2',
        'effective_date' => 'date',
        'end_date' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class, 'employee_id');
    }
}
