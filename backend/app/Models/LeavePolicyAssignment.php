<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeavePolicyAssignment extends Model
{
    protected $fillable = [
        'company_id',
        'policy_id',
        'employee_id',
        'effective_date',
        'end_date',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'policy_id' => 'integer',
            'employee_id' => 'integer',
            'effective_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(LeavePolicy::class, 'policy_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }
}
