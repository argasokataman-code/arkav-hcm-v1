<?php

namespace App\Models;
use App\Models\Concerns\AssignsUuid;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeBenefit extends Model
{
    use AssignsUuid;

    protected $table = 'employee_benefits';

    protected $fillable = [
        'employee_id',
        'bpjs_kesehatan_no',
        'bpjs_ketenagakerjaan_no',
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
