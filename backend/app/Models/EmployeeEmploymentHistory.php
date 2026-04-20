<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class EmployeeEmploymentHistory extends Model
{
    use AssignsUuid;

    protected $table = 'employee_employment_history';

    protected $fillable = [
        'employee_id',
        'employment_status',
        'employee_type',
        'start_date',
        'end_date',
        'probation_end_date',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'probation_end_date' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class, 'employee_id');
    }
}
