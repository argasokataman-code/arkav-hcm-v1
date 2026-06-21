<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class HcmUserRole extends Model
{
    use AssignsUuid;

    protected static function booted(): void
    {
        static::saving(function (HcmUserRole $assignment): void {
            if (Schema::hasColumn($assignment->getTable(), 'user_uuid') && ! $assignment->user_uuid && $assignment->user_id) {
                $assignment->user_uuid = (string) (User::query()->where('id', $assignment->user_id)->value('uuid') ?? '');
            }

            if (Schema::hasColumn($assignment->getTable(), 'company_uuid') && ! $assignment->company_uuid && $assignment->company_id) {
                $assignment->company_uuid = (string) (Company::query()->where('id', $assignment->company_id)->value('uuid') ?? '');
            }

            if (Schema::hasColumn($assignment->getTable(), 'assigned_by_user_uuid') && ! $assignment->assigned_by_user_uuid && $assignment->assigned_by_user_id) {
                $assignment->assigned_by_user_uuid = (string) (User::query()->where('id', $assignment->assigned_by_user_id)->value('uuid') ?? '');
            }
        });
    }

    protected $fillable = [
        'uuid',
        'user_id',
        'user_uuid',
        'company_id',
        'company_uuid',
        'role_id',
        'assigned_by_user_id',
        'assigned_by_user_uuid',
        'status',
        'effective_from',
        'effective_until',
        'revoked_at',
    ];

    protected $casts = [
        'uuid' => 'string',
        'user_id' => 'integer',
        'user_uuid' => 'string',
        'company_id' => 'integer',
        'company_uuid' => 'string',
        'role_id' => 'integer',
        'assigned_by_user_id' => 'integer',
        'assigned_by_user_uuid' => 'string',
        'effective_from' => 'date',
        'effective_until' => 'date',
        'revoked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(HcmRole::class, 'role_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }
}
