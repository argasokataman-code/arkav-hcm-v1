<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HcmBillingTaxPolicy extends Model
{
    use AssignsUuid, HasFactory;

    protected $table = 'hcm_billing_tax_policies';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'company_id',
        'billing_month',
        'billing_cycle_type',
        'tax_rate_percentage',
        'base_calculation_method',
        'effective_from',
        'effective_to',
        'status',
        'notes',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'tax_rate_percentage' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'created_by_user_id' => 'integer',
            'updated_by_user_id' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
