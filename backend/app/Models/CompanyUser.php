<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class CompanyUser extends Model
{
    use AssignsUuid;

    protected static function booted(): void
    {
        static::saving(function (CompanyUser $membership): void {
            if (Schema::hasColumn($membership->getTable(), 'user_uuid') && ! $membership->user_uuid && $membership->user_id) {
                $membership->user_uuid = (string) (User::query()->where('id', $membership->user_id)->value('uuid') ?? '');
            }

            if (Schema::hasColumn($membership->getTable(), 'company_uuid') && ! $membership->company_uuid && $membership->company_id) {
                $membership->company_uuid = (string) (Company::query()->where('id', $membership->company_id)->value('uuid') ?? '');
            }

            if (Schema::hasColumn($membership->getTable(), 'invited_by_user_uuid') && ! $membership->invited_by_user_uuid && $membership->invited_by_user_id) {
                $membership->invited_by_user_uuid = (string) (User::query()->where('id', $membership->invited_by_user_id)->value('uuid') ?? '');
            }
        });
    }

    protected $fillable = [
        'uuid',
        'company_id',
        'company_uuid',
        'user_id',
        'user_uuid',
        'role',
        'status',
        'joined_at',
        'invited_by_user_id',
        'invited_by_user_uuid',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'user_id' => 'integer',
        'invited_by_user_id' => 'integer',
        'uuid' => 'string',
        'company_uuid' => 'string',
        'user_uuid' => 'string',
        'invited_by_user_uuid' => 'string',
        'joined_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }
}
