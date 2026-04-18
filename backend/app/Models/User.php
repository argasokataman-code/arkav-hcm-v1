<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\PasswordResetLinkNotification;
use App\Models\Concerns\AssignsUuid;
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
        $activeCompanyIds = CompanyUser::query()
            ->where('user_id', $this->id)
            ->where('status', 'active')
            ->pluck('company_id')
            ->map(static fn ($value): int => (int) $value)
            ->all();

        foreach ($activeCompanyIds as $companyId) {
            if ($this->isHcmAdminForCompany($companyId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user is HCM admin for a specific company via tenant membership + RBAC role assignment.
     */
    public function isHcmAdminForCompany(int $companyId): bool
    {
        if ($companyId <= 0) {
            return false;
        }

        if (! $this->hasActiveMembershipForCompany($companyId)) {
            return false;
        }

        if ($this->isOwnerForCompany($companyId)) {
            return true;
        }

        return $this->hasActiveAdminAssignmentForCompany($companyId);
    }

    private function isOwnerForCompany(int $companyId): bool
    {
        return CompanyUser::query()
            ->where('user_id', $this->id)
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->where('role', 'owner')
            ->exists();
    }

    private function hasActiveMembershipForCompany(int $companyId): bool
    {
        return CompanyUser::query()
            ->where('user_id', $this->id)
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->exists();
    }

    private function hasActiveAdminAssignmentForCompany(int $companyId): bool
    {
        $today = now()->toDateString();

        return HcmUserRole::query()
            ->where('user_id', $this->id)
            ->where('company_id', $companyId)
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
     * @return array<int, string>
     */
    private function hcmAdminRoleCodes(): array
    {
        return ['ADMIN', 'HR_ADMIN', 'OPS_ADMIN', 'HCM_ADMIN'];
    }
}
