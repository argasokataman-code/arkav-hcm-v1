<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    protected $fillable = [
        'company_id',
        'code',
        'name',
        'is_paid',
        'requires_approval',
        'requires_attachment',
        'deduct_from_balance',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'is_paid' => 'boolean',
            'requires_approval' => 'boolean',
            'requires_attachment' => 'boolean',
            'deduct_from_balance' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function policies(): HasMany
    {
        return $this->hasMany(LeavePolicy::class, 'leave_type_id');
    }
}
