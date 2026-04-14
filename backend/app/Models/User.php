<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\PasswordResetLinkNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

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
     */
    public function isHcmAdmin(): bool
    {
        $email = strtolower(trim((string) ($this->email ?? '')));
        $adminEmail = strtolower(trim((string) config('hcm.admin_email', 'qa.login@example.com')));
        if ($email === $adminEmail) {
            return true;
        }

        $this->loadMissing('employeeProfile.department', 'employeeProfile.designationRef');

        $designation = strtolower((string) ($this->employeeProfile?->designationRef?->name ?: $this->employeeProfile?->designation ?? ''));
        $team = strtolower((string) ($this->employeeProfile?->department?->name ?: $this->employeeProfile?->team ?? ''));

        // NOTE: "manager" is a separate role in Phase-1 performance workflow (not HCM Admin).
        $adminKeywords = ['admin', 'hr', 'lead', 'supervisor', 'head', 'owner'];
        foreach ($adminKeywords as $keyword) {
            if (str_contains($designation, $keyword) || str_contains($team, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user is HCM admin for a specific company.
     * Global admins have access to all companies.
     * Company-specific admins must have an active admin role assignment.
     */
    public function isHcmAdminForCompany(int $companyId): bool
    {
        // Global super-admin
        if ($this->isHcmAdmin()) {
            return true;
        }

        // Check if user has an active admin-level role in this specific company
        return HcmUserRole::query()
            ->where('user_id', $this->id)
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->whereHas('role', function ($q) {
                // Only ADMIN, HR_ADMIN, OPS_ADMIN roles grant HCM access
                $q->whereIn('code', ['ADMIN', 'HR_ADMIN', 'OPS_ADMIN']);
            })
            ->exists();
    }
}
