<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeBankAccount extends Model
{
    protected $table = 'employee_bank_accounts';

    protected $fillable = [
        'employee_id',
        'bank_name',
        'account_number',
        'account_holder_name',
        'bank_ifsc_code',
        'bank_branch',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class, 'employee_id');
    }
}
