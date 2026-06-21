<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HcmRole extends Model
{
    use AssignsUuid;

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

    /**
     * @param  iterable<int, int|string>|Collection<int, mixed>  $permissionIds
     */
    public function scopePlatform(Builder $query): Builder
    {
        return $query->whereNull('company_id');
    }

    public function syncPermissionsForCompany(iterable $permissionIds): void
    {
        $normalizedPermissionIds = collect($permissionIds)
            ->map(static fn ($permissionId): int => (int) $permissionId)
            ->filter(static fn (int $permissionId): bool => $permissionId > 0)
            ->unique()
            ->values();

        $companyUuid = null;
        if ($this->company_id !== null && Schema::hasColumn('hcm_role_permissions', 'company_uuid')) {
            $companyUuid = Company::query()->where('id', $this->company_id)->value('uuid');
        }

        DB::transaction(function () use ($normalizedPermissionIds, $companyUuid): void {
            $existingMappings = HcmRolePermission::query()->where('role_id', $this->id);
            if ($this->company_id !== null) {
                $existingMappings->where('company_id', $this->company_id);
            }

            $existingMappings->delete();

            if ($normalizedPermissionIds->isEmpty()) {
                return;
            }

            $timestamp = now();
            HcmRolePermission::query()->insert(
                $normalizedPermissionIds
                    ->map(function (int $permissionId) use ($timestamp, $companyUuid): array {
                        return [
                            'role_id' => $this->id,
                            'permission_id' => $permissionId,
                            'company_id' => $this->company_id,
                            'company_uuid' => $companyUuid,
                            'created_at' => $timestamp,
                        ];
                    })
                    ->all()
            );
        });
    }
}
