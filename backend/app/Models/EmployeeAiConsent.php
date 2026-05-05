<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Track AI Chat consent per employee.
 * UU PDP H3 finding: Employees must explicitly consent before AI Chat uses external service.
 *
 * @property int $id
 * @property string $employee_uuid UUID of EmployeeProfile
 * @property string|null $user_uuid UUID of User giving consent
 * @property \Carbon\Carbon $consent_given_at When employee gave consent
 * @property string|null $consent_ip_address IP address of consent
 * @property string|null $consent_text Snapshot of consent notice
 * @property \Carbon\Carbon|null $withdrawn_at When employee withdrew consent (NULL = active)
 */
class EmployeeAiConsent extends Model
{
    protected $table = 'employee_ai_consents';

    protected $fillable = [
        'employee_uuid',
        'user_uuid',
        'consent_given_at',
        'consent_ip_address',
        'consent_text',
        'withdrawn_at',
    ];

    protected $casts = [
        'consent_given_at' => 'datetime',
        'withdrawn_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class, 'employee_uuid', 'uuid');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }

    /**
     * Check if employee has active AI consent (not withdrawn).
     */
    public function isActive(): bool
    {
        return $this->withdrawn_at === null;
    }

    /**
     * Get active consent for an employee.
     */
    public static function getActiveForEmployee(string $employeeUuid): ?self
    {
        return static::query()
            ->where('employee_uuid', $employeeUuid)
            ->whereNull('withdrawn_at')
            ->latest('consent_given_at')
            ->first();
    }
}
