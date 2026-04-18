<?php
namespace App\Models;

use App\Models\Concerns\AssignsUuid;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyUser extends Model
{
    use AssignsUuid;
    protected $fillable = [
        'company_id',
        'user_id',
        'role',
        'status',
        'joined_at',
        'invited_by_user_id',
    ];

    protected $casts = [
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
