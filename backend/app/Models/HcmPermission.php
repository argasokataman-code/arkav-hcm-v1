<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Schema;

class HcmPermission extends Model
{
    use AssignsUuid;

    protected $fillable = [
        'code',
        'module',
        'resource',
        'action',
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];


    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(HcmRole::class, 'hcm_role_permissions', 'permission_id', 'role_id');
    }
}
