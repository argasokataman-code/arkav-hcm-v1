<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeEducation extends Model
{
    use AssignsUuid;

    protected $table = 'employee_educations';

    protected $fillable = [
        'employee_id',
        'institution',
        'degree',
        'field_of_study',
        'start_year',
        'end_year',
        'notes',
        'sort_order',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class, 'employee_id');
    }
}
