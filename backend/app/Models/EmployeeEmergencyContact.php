<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeEmergencyContact extends Model
{
    protected $table = 'employee_emergency_contacts';

    protected $fillable = [
        'employee_id',
        'name',
        'relationship',
        'phone',
        'email',
        'sort_order',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class, 'employee_id');
    }
}
