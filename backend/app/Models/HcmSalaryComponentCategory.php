<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HcmSalaryComponentCategory extends Model
{
    use AssignsUuid;

    protected $table = 'hcm_salary_component_categories';

    protected $fillable = [
        'kind',
        'code',
        'name',
        'description',
        'is_system',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function salaryComponents(): HasMany
    {
        return $this->hasMany(HcmSalaryComponent::class, 'category_uuid', 'uuid');
    }
}
