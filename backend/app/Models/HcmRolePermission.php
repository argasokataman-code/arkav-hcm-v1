<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Schema;

class HcmRolePermission extends Model
{
    use AssignsUuid;

    protected $fillable = [
        'role_id',
        'permission_id',
        'company_id', // Added for tenant-scoped mappings
        'company_uuid',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'company_uuid' => 'string',
    ];

    protected static function booted(): void
    {

        // Ensure tenant isolation: role-permission mappings must have company_id for tenant roles
        static::saving(function (HcmRolePermission $mapping): void {
            if ($mapping->company_id === null) {
                $role = HcmRole::find($mapping->role_id);
                if ($role && $role->company_id !== null) {
                    $mapping->company_id = $role->company_id;
                }
            }

            if (Schema::hasColumn($mapping->getTable(), 'company_uuid') && ! $mapping->company_uuid && $mapping->company_id) {
                $mapping->company_uuid = (string) (Company::query()->where('id', $mapping->company_id)->value('uuid') ?? '');
            }
        });
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(HcmRole::class);
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(HcmPermission::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
