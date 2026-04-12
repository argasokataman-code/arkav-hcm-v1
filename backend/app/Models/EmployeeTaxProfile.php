<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeTaxProfile extends Model
{
    protected $table = 'employee_tax_profiles';

    protected $fillable = [
        'employee_id',
        'npwp',
        'tax_status',
        'ptkp_status',
        'effective_date',
        'end_date',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'end_date' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class, 'employee_id');
    }
}
