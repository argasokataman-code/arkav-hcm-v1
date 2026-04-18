<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class HcmRole extends Model
{
    protected $fillable = [
        'company_id',
        'code',
        'name',
        'description',
        'status',
        'is_system',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'is_system' => 'boolean',
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

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(HcmPermission::class, 'hcm_role_permissions', 'role_id', 'permission_id');
    }

    public function userAssignments(): HasMany
    {
        return $this->hasMany(HcmUserRole::class, 'role_id');
    }
}
