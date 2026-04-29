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
        'submitted_by_user_id',
        'submitted_at',
        'approved_by_user_id',
        'approved_at',
        'published_by_user_id',
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

    public function events(): HasMany
    {
        return $this->hasMany(HcmTaxGovernancePolicyEvent::class, 'hcm_tax_governance_policy_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
