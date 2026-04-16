    protected static function booted()
    {
        static::addGlobalScope('company', function ($query) {
            if (auth()->check() && auth()->user()->employeeProfile) {
                $query->where('company_id', auth()->user()->employeeProfile->company_id);
            }
        });
    }
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HcmPayrollPeriod extends Model
{
    public const STATUS_OPEN = 'open';

    /** Set when a run for this period has been finalized (posted payslip). */
    public const STATUS_POSTED = 'posted';

    protected $fillable = [
        'company_id',
        'period_year',
        'period_month',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'period_year' => 'integer',
            'period_month' => 'integer',
        ];
    }

    public function runs(): HasMany
    {
        return $this->hasMany(HcmPayrollRun::class, 'hcm_payroll_period_id');
    }
}
