<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HcmPayrollItem extends Model
{
    protected $fillable = [
        'company_id',
        'hcm_salary_component_id',
        'code',
        'name',
        'kind',
        'category',
        'notes',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function salaryComponent(): BelongsTo
    {
        return $this->belongsTo(HcmSalaryComponent::class, 'hcm_salary_component_id');
    }
}
