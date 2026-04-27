<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HcmTaxGovernancePolicyEvent extends Model
{
    use AssignsUuid;

    protected $fillable = [
        'company_id',
        'hcm_tax_governance_policy_id',
        'event_type',
        'actor_user_id',
        'before_state',
        'after_state',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'hcm_tax_governance_policy_id' => 'integer',
            'actor_user_id' => 'integer',
            'before_state' => 'array',
            'after_state' => 'array',
        ];
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(HcmTaxGovernancePolicy::class, 'hcm_tax_governance_policy_id');
    }

    public function actorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function actor(): BelongsTo
    {
        return $this->actorUser();
    }
}
