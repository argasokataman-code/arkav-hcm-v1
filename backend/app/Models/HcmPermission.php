<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class HcmPermission extends Model
{
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

    protected static function booted(): void
    {
        static::creating(function (self $record): void {
            if (! Schema::hasColumn($record->getTable(), 'uuid')) {
                return;
            }

            if (empty($record->uuid)) {
                $record->uuid = (string) Str::uuid();
            }
        });
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(HcmRole::class, 'hcm_role_permissions', 'permission_id', 'role_id');
    }
}
