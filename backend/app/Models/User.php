<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\PasswordResetLinkNotification;
use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, AssignsUuid;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function employeeProfile(): HasOne
    {
        return $this->hasOne(EmployeeProfile::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function companyMemberships(): HasMany
    {
        return $this->hasMany(CompanyUser::class);
    }

    public function sendPasswordResetNotification($token): void
    {
        $url = url('/reset-password/'.$token).'?email='.urlencode($this->email);

        $this->notify(new PasswordResetLinkNotification($url));
    }

    /**
     * Mirrors {@see \App\Http\Controllers\Api\Concerns\EnsuresHcmAdmin} for API and `/auth/me` hints.
     * Global check now relies on RBAC assignment in at least one active company.
     */
    public function isHcmAdmin(): bool
    {
        if ($this->isGlobalHcmAdminSignal()) {
            return true;
        }

        $activeCompanyIdentifiers = CompanyUser::query()
            ->where(function (Builder $query): void {
                $this->applyUserIdentifierScope($query, 'user_id', 'user_uuid');
            })
            ->where('status', 'active')
            ->select(['company_id', 'company_uuid'])
            ->get()
            ->map(static function ($membership): string {
                $companyUuid = (string) ($membership->company_uuid ?? '');
                if ($companyUuid !== '') {
                    return $companyUuid;
                }

                return (string) ((int) ($membership->company_id ?? 0));
            })
            ->filter(static fn (string $value): bool => $value !== '' && $value !== '0')
            ->unique()
            ->values()
            ->all();

        foreach ($activeCompanyIdentifiers as $companyIdentifier) {
            if ($this->hasActiveAdminAssignmentForCompany($companyIdentifier)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user is HCM admin for a specific company via tenant membership + RBAC role assignment.
     */
    public function isHcmAdminForCompany(string|int $companyIdentifier): bool
    {
        if ($this->isInvalidCompanyIdentifier($companyIdentifier)) {
            return false;
        }

        if ($this->isGlobalHcmAdminSignal()) {
            return true;
        }

        if (! $this->hasActiveMembershipForCompany($companyIdentifier)) {
            return false;
        }

        if ($this->isOwnerForCompany($companyIdentifier)) {
            return true;
        }

        if ($this->isAdminMembershipForCompany($companyIdentifier)) {
            return true;
        }

        // Preserve legacy admin detection for users that still rely on profile keywords,
        // but only within companies where they have an active membership.
        if ($this->hasLegacyTenantHcmAdminSignal()) {
            return true;
        }

        return $this->hasActiveAdminAssignmentForCompany($companyIdentifier);
    }

    public function isGlobalHcmAdmin(): bool
    {
        return $this->isGlobalHcmAdminSignal();
    }

    private function hasLegacyTenantHcmAdminSignal(): bool
    {
        $this->loadMissing('employeeProfile.department', 'employeeProfile.designationRef');

        $designation = strtolower((string) ($this->employeeProfile?->designationRef?->name ?: $this->employeeProfile?->designation ?? ''));
        $team = strtolower((string) ($this->employeeProfile?->department?->name ?: $this->employeeProfile?->team ?? ''));

        $tenantKeywords = ['admin', 'hr', 'owner'];
        foreach ($tenantKeywords as $keyword) {
            if (str_contains($designation, $keyword) || str_contains($team, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function isGlobalHcmAdminSignal(): bool
    {
        $email = strtolower(trim((string) ($this->email ?? '')));
        $adminEmail = strtolower(trim((string) config('hcm.admin_email', 'qa.login@example.com')));
        if ($email !== '' && $email === $adminEmail) {
            return true;
        }

        return $this->hasLegacyGlobalHcmAdminSignal();
    }

    private function hasLegacyGlobalHcmAdminSignal(): bool
    {
        $this->loadMissing('employeeProfile.department', 'employeeProfile.designationRef');

        $designation = strtolower((string) ($this->employeeProfile?->designationRef?->name ?: $this->employeeProfile?->designation ?? ''));
        $team = strtolower((string) ($this->employeeProfile?->department?->name ?: $this->employeeProfile?->team ?? ''));

        // Keep only explicit global-admin legacy signals here; tenant-level roles are resolved separately.
        $adminKeywords = ['admin', 'hr'];
        foreach ($adminKeywords as $keyword) {
            if (str_contains($designation, $keyword) || str_contains($team, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function isOwnerForCompany(string|int $companyIdentifier): bool
    {
        return CompanyUser::query()
            ->where(function (Builder $query): void {
                $this->applyUserIdentifierScope($query, 'user_id', 'user_uuid');
            })
            ->where(function (Builder $query) use ($companyIdentifier): void {
                $this->applyCompanyIdentifierScope($query, $companyIdentifier, 'company_id', 'company_uuid');
            })
            ->where('status', 'active')
            ->where('role', 'owner')
            ->exists();
    }

    private function isAdminMembershipForCompany(string|int $companyIdentifier): bool
    {
        return CompanyUser::query()
            ->where(function (Builder $query): void {
                $this->applyUserIdentifierScope($query, 'user_id', 'user_uuid');
            })
            ->where(function (Builder $query) use ($companyIdentifier): void {
                $this->applyCompanyIdentifierScope($query, $companyIdentifier, 'company_id', 'company_uuid');
            })
            ->where('status', 'active')
            ->where('role', 'admin')
            ->exists();
    }

    private function hasActiveMembershipForCompany(string|int $companyIdentifier): bool
    {
        return CompanyUser::query()
            ->where(function (Builder $query): void {
                $this->applyUserIdentifierScope($query, 'user_id', 'user_uuid');
            })
            ->where(function (Builder $query) use ($companyIdentifier): void {
                $this->applyCompanyIdentifierScope($query, $companyIdentifier, 'company_id', 'company_uuid');
            })
            ->where('status', 'active')
            ->exists();
    }

    private function hasActiveAdminAssignmentForCompany(string|int $companyIdentifier): bool
    {
        $today = now()->toDateString();

        return HcmUserRole::query()
            ->where(function (Builder $query): void {
                $this->applyUserIdentifierScope($query, 'user_id', 'user_uuid');
            })
            ->where(function (Builder $query) use ($companyIdentifier): void {
                $this->applyCompanyIdentifierScope($query, $companyIdentifier, 'company_id', 'company_uuid');
            })
            ->where('status', 'active')
            ->where(function ($query) use ($today): void {
                $query->whereNull('effective_from')
                    ->orWhere('effective_from', '<=', $today);
            })
            ->where(function ($query) use ($today): void {
                $query->whereNull('effective_until')
                    ->orWhere('effective_until', '>=', $today);
            })
            ->whereHas('role', function ($q) {
                $q->whereIn('code', $this->hcmAdminRoleCodes());
            })
            ->exists();
    }

    /**
     * Check if user has a specific permission for a company through their active roles.
     */
    public function hasPermissionForCompany(string $permissionCode, string|int $companyIdentifier): bool
    {
        $today = now()->toDateString();

        return HcmUserRole::query()
            ->where(function (Builder $query): void {
                $this->applyUserIdentifierScope($query, 'user_id', 'user_uuid');
            })
            ->where(function (Builder $query) use ($companyIdentifier): void {
                $this->applyCompanyIdentifierScope($query, $companyIdentifier, 'company_id', 'company_uuid');
            })
            ->where('status', 'active')
            ->where(function ($query) use ($today): void {
                $query->whereNull('effective_from')
                    ->orWhere('effective_from', '<=', $today);
            })
            ->where(function ($query) use ($today): void {
                $query->whereNull('effective_until')
                    ->orWhere('effective_until', '>=', $today);
            })
            ->whereHas('role.permissions', function ($query) use ($permissionCode): void {
                $query->where('code', $permissionCode);
            })
            ->exists();
    }

    /**
     * Get all active permission codes for a company through the user's active roles.
     *
     * @return array<string, bool>
     */
    public function permissionsForCompany(string|int $companyIdentifier): array
    {
        if ($this->isInvalidCompanyIdentifier($companyIdentifier)) {
            return [];
        }

        $today = now()->toDateString();

        $codes = HcmUserRole::query()
            ->where(function (Builder $query): void {
                $this->applyUserIdentifierScope($query, 'user_id', 'user_uuid');
            })
            ->where(function (Builder $query) use ($companyIdentifier): void {
                $this->applyCompanyIdentifierScope($query, $companyIdentifier, 'company_id', 'company_uuid');
            })
            ->where('status', 'active')
            ->where(function ($query) use ($today): void {
                $query->whereNull('effective_from')
                    ->orWhere('effective_from', '<=', $today);
            })
            ->where(function ($query) use ($today): void {
                $query->whereNull('effective_until')
                    ->orWhere('effective_until', '>=', $today);
            })
            ->whereHas('role.permissions', function ($query): void {
                $query->where('is_active', true);
            })
            ->with(['role.permissions' => function ($query): void {
                $query->select('hcm_permissions.id', 'hcm_permissions.code')->where('is_active', true);
            }])
            ->get()
            ->flatMap(function (HcmUserRole $assignment): array {
                return $assignment->role?->permissions?->pluck('code')->all() ?? [];
            })
            ->unique()
            ->values()
            ->all();

        return array_fill_keys($codes, true);
    }

    /**
     * Get permissions for the current auth context.
     * If a company is provided, return that company's permissions.
     * Otherwise, merge permissions from all active company memberships.
     *
     * @return array<string, bool>
     */
    public function permissionsForContext(string|int|null $companyId = null): array
    {
        if ($companyId !== null && ! $this->isInvalidCompanyIdentifier($companyId)) {
            return $this->permissionsForCompany($companyId);
        }

        $companyIds = CompanyUser::query()
            ->where(function (Builder $query): void {
                $this->applyUserIdentifierScope($query, 'user_id', 'user_uuid');
            })
            ->where('status', 'active')
            ->select(['company_id', 'company_uuid'])
            ->get()
            ->map(static function ($membership): string {
                $companyUuid = (string) ($membership->company_uuid ?? '');
                if ($companyUuid !== '') {
                    return $companyUuid;
                }

                return (string) ((int) ($membership->company_id ?? 0));
            })
            ->filter(static fn (string $value): bool => $value !== '' && $value !== '0')
            ->unique()
            ->values()
            ->all();

        $merged = [];
        foreach ($companyIds as $companyIdItem) {
            $merged = array_merge($merged, array_keys($this->permissionsForCompany($companyIdItem)));
        }

        return array_fill_keys(array_values(array_unique($merged)), true);
    }

    /**
     * @return array<int, string>
     */
    private function hcmAdminRoleCodes(): array
    {
        return ['ADMIN', 'HR_ADMIN', 'OPS_ADMIN', 'HCM_ADMIN'];
    }

    private function applyUserIdentifierScope(Builder $query, string $idColumn, string $uuidColumn): void
    {
        $hasLegacyId = $this->id !== null;
        $hasUuid = (string) ($this->uuid ?? '') !== '';

        if ($hasLegacyId && $hasUuid) {
            $query->where(function (Builder $nested) use ($idColumn, $uuidColumn): void {
                $nested->where($idColumn, $this->id)
                    ->orWhere($uuidColumn, $this->uuid);
            });

            return;
        }

        if ($hasUuid) {
            $query->where($uuidColumn, $this->uuid);

            return;
        }

        if ($hasLegacyId) {
            $query->where($idColumn, $this->id);
        }
    }

    private function applyCompanyIdentifierScope(Builder $query, string|int $companyIdentifier, string $idColumn, string $uuidColumn): void
    {
        $normalized = trim((string) $companyIdentifier);
        if ($normalized === '') {
            return;
        }

        if ($this->looksLikeUuid($normalized)) {
            $query->where($uuidColumn, $normalized);

            return;
        }

        $numericId = (int) $normalized;
        if ($numericId > 0) {
            $query->where($idColumn, $numericId);
        }
    }

    private function isInvalidCompanyIdentifier(string|int $companyIdentifier): bool
    {
        $normalized = trim((string) $companyIdentifier);
        if ($normalized === '') {
            return true;
        }

        if ($this->looksLikeUuid($normalized)) {
            return false;
        }

        return ((int) $normalized) <= 0;
    }

    private function looksLikeUuid(string $value): bool
    {
        return preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-8][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$/', $value) === 1;
    }
}
