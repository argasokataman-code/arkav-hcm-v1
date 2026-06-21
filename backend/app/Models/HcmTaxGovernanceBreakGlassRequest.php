<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HcmTaxGovernanceBreakGlassRequest extends Model
{
    use AssignsUuid;

    protected $fillable = [
        'uuid',
        'target_company_id',
        'target_company_uuid',
        'requested_by_user_id',
        'requested_by_user_uuid',
        'approved_by_user_id',
        'approved_by_user_uuid',
        'reason_code',
        'reason',
        'approval_note',
        'status',
        'expires_at',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'target_company_id' => 'integer',
            'requested_by_user_id' => 'integer',
            'approved_by_user_id' => 'integer',
            'expires_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $model): void {
            if (! $model->target_company_uuid && $model->target_company_id) {
                $model->target_company_uuid = Company::query()->where('id', (int) $model->target_company_id)->value('uuid');
            }

            if (! $model->requested_by_user_uuid && $model->requested_by_user_id) {
                $model->requested_by_user_uuid = User::query()->where('id', (int) $model->requested_by_user_id)->value('uuid');
            }

            if (! $model->approved_by_user_uuid && $model->approved_by_user_id) {
                $model->approved_by_user_uuid = User::query()->where('id', (int) $model->approved_by_user_id)->value('uuid');
            }
        });
    }

    public function targetCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'target_company_uuid', 'uuid');
    }
}
