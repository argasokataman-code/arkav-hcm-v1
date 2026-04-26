<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OvertimeRequest extends Model
{
    use AssignsUuid;

    protected $fillable = [
        'user_id',
        'company_id',
        'hcm_overtime_type_id',
        'hcm_salary_component_id',
        'request_type',
        'work_date',
        'minutes',
        'day_type',
        'weekly_work_days',
        'project_name',
        'status',
        'approved_by_user_id',
        'approved_at',
        'notes',
        'policy_note',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'weekly_work_days' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function overtimeType(): BelongsTo
    {
        return $this->belongsTo(HcmOvertimeType::class, 'hcm_overtime_type_id');
    }

    public function salaryComponent(): BelongsTo
    {
        return $this->belongsTo(HcmSalaryComponent::class, 'hcm_salary_component_id');
    }
}
