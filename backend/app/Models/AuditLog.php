<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $table = 'audit_logs';

    protected $fillable = [
        'super_admin_id',
        'action',
        'target_type',
        'target_id',
        'details',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'details' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the super admin who performed the action
     */
    public function superAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'super_admin_id');
    }

    /**
     * Determine if this is a sensitive action
     */
    public function isSensitiveAction(): bool
    {
        return in_array($this->action, [
            'delete_company',
            'refund_transaction',
            'modify_subscription',
            'reset_user_password',
            'delete_user',
            'modify_billing',
        ]);
    }

    /**
     * Get human-readable action label
     */
    public function getActionLabel(): string
    {
        return match ($this->action) {
            'view_dashboard' => 'Viewed Dashboard',
            'modify_subscription' => 'Modified Subscription',
            'delete_company' => 'Deleted Company',
            'refund_transaction' => 'Processed Refund',
            'reset_user_password' => 'Reset Password',
            'delete_user' => 'Deleted User',
            'modify_billing' => 'Modified Billing',
            'export_report' => 'Exported Report',
            'view_audit_logs' => 'Viewed Audit Logs',
            default => ucfirst(str_replace('_', ' ', $this->action)),
        };
    }
}
