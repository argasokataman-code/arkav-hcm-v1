<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollSettingsAuditLog extends Model
{
    use AssignsUuid;

    protected $table = 'payroll_settings_audit_log';

    protected $fillable = [
        'uuid',
        'company_id',
        'user_id',
        'action',
        'setting_key',
        'old_value',
        'new_value',
        'changed_at',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'user_id' => 'integer',
            'changed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the company this audit log entry belongs to
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the user who made the change
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
