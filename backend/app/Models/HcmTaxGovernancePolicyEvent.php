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
        'company_uuid',
        'hcm_tax_governance_policy_id',
        'hcm_tax_governance_policy_uuid',
        'event_type',
        'actor_user_id',
        'actor_user_uuid',
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

    protected static function booted(): void
    {
        static::saving(function (self $model): void {
            if (! $model->company_uuid && $model->company_id) {
                $model->company_uuid = Company::query()
                    ->where('id', (int) $model->company_id)
                    ->value('uuid');
            }

            if (! $model->hcm_tax_governance_policy_uuid && $model->hcm_tax_governance_policy_id) {
                $model->hcm_tax_governance_policy_uuid = HcmTaxGovernancePolicy::query()
                    ->where('id', (int) $model->hcm_tax_governance_policy_id)
                    ->value('uuid');
            }

            if (! $model->actor_user_uuid && $model->actor_user_id) {
                $model->actor_user_uuid = User::query()
                    ->where('id', (int) $model->actor_user_id)
                    ->value('uuid');
            }
        });
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(HcmTaxGovernancePolicy::class, 'hcm_tax_governance_policy_uuid', 'uuid');
    }

    public function actorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_uuid', 'uuid');
    }

    public function actor(): BelongsTo
    {
        return $this->actorUser();
    }
}
