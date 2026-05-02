<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HcmTaxGovernancePolicy extends Model
{
    use AssignsUuid, HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_SUPERSEDED = 'superseded';

    public const STATUS_VOID = 'void';

    protected $fillable = [
        'company_id',
        'company_uuid',
        'policy_code',
        'name',
        'status',
        'effective_start_date',
        'effective_end_date',
        'rules',
        'rate_schedules',
        'draft_fingerprint',
        'version',
        'created_by_user_id',
        'created_by_user_uuid',
        'submitted_by_user_id',
        'submitted_by_user_uuid',
        'submitted_at',
        'approved_by_user_id',
        'approved_by_user_uuid',
        'approved_at',
        'published_by_user_id',
        'published_by_user_uuid',
        'published_at',
        'last_note',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'effective_start_date' => 'date',
            'effective_end_date' => 'date',
            'rules' => 'array',
            'rate_schedules' => 'array',
            'version' => 'integer',
            'created_by_user_id' => 'integer',
            'submitted_by_user_id' => 'integer',
            'submitted_at' => 'datetime',
            'approved_by_user_id' => 'integer',
            'approved_at' => 'datetime',
            'published_by_user_id' => 'integer',
            'published_at' => 'datetime',
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

            $model->created_by_user_uuid = self::resolveUserUuid($model->created_by_user_uuid, $model->created_by_user_id);
            $model->submitted_by_user_uuid = self::resolveUserUuid($model->submitted_by_user_uuid, $model->submitted_by_user_id);
            $model->approved_by_user_uuid = self::resolveUserUuid($model->approved_by_user_uuid, $model->approved_by_user_id);
            $model->published_by_user_uuid = self::resolveUserUuid($model->published_by_user_uuid, $model->published_by_user_id);
        });
    }

    public function events(): HasMany
    {
        return $this->hasMany(HcmTaxGovernancePolicyEvent::class, 'hcm_tax_governance_policy_uuid', 'uuid');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_uuid', 'uuid');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_uuid', 'uuid');
    }

    private static function resolveUserUuid(?string $currentUuid, mixed $legacyId): ?string
    {
        if (is_string($currentUuid) && $currentUuid !== '') {
            return $currentUuid;
        }

        if (! is_numeric($legacyId) || (int) $legacyId <= 0) {
            return $currentUuid;
        }

        return User::query()->where('id', (int) $legacyId)->value('uuid');
    }
}
