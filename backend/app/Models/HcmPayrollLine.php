<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class HcmPayrollLine extends Model
{
    use AssignsUuid;

    protected static function booted(): void
    {
        static::saving(function (self $line): void {
            if (Schema::hasColumn($line->getTable(), 'company_uuid') && ! $line->company_uuid && $line->company_id) {
                $line->company_uuid = (string) (Company::query()->where('id', $line->company_id)->value('uuid') ?? '');
            }

            if (Schema::hasColumn($line->getTable(), 'user_uuid') && ! $line->user_uuid && $line->user_id) {
                $line->user_uuid = (string) (User::query()->where('id', $line->user_id)->value('uuid') ?? '');
            }

            if (Schema::hasColumn($line->getTable(), 'hcm_payroll_run_uuid') && ! $line->hcm_payroll_run_uuid && $line->hcm_payroll_run_id) {
                $line->hcm_payroll_run_uuid = (string) (HcmPayrollRun::query()->where('id', $line->hcm_payroll_run_id)->value('uuid') ?? '');
            }

            if (Schema::hasColumn($line->getTable(), 'hcm_salary_component_uuid') && ! $line->hcm_salary_component_uuid && $line->hcm_salary_component_id) {
                $line->hcm_salary_component_uuid = (string) (HcmSalaryComponent::query()->where('id', $line->hcm_salary_component_id)->value('uuid') ?? '');
            }
        });
    }

    protected $fillable = [
        'company_id',
        'hcm_payroll_run_id',
        'user_id',
        'hcm_salary_component_id',
        'component_code',
        'component_name',
        'kind',
        'category',
        'amount',
        'sort_order',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'amount' => 'decimal:2',
            'meta' => 'array',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(HcmPayrollRun::class, 'hcm_payroll_run_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function salaryComponent(): BelongsTo
    {
        return $this->belongsTo(HcmSalaryComponent::class, 'hcm_salary_component_id');
    }
}
