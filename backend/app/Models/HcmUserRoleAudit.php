<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HcmUserRoleAudit extends Model
{
    use AssignsUuid;

    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'actor_user_id',
        'target_user_id',
        'role_id',
        'action',
        'notes',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'actor_user_id' => 'integer',
        'target_user_id' => 'integer',
        'role_id' => 'integer',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function actorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(HcmRole::class, 'role_id');
    }
}
