<?php
namespace App\Models;

use App\Models\Concerns\AssignsUuid;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HcmUserRole extends Model
{
    use AssignsUuid;
    protected $fillable = [
        'user_id',
        'company_id',
        'role_id',
        'assigned_by_user_id',
        'status',
        'effective_from',
        'effective_until',
        'revoked_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'company_id' => 'integer',
        'role_id' => 'integer',
        'assigned_by_user_id' => 'integer',
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
