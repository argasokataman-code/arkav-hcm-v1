<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollSettingsSnapshot extends Model
{
    use AssignsUuid;

    protected $fillable = [
        'uuid',
        'company_uuid',
        'snapshot_version',
        'user_uuid',
        'settings_data',
        'change_reason',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_version' => 'integer',
            'settings_data' => 'array',
            'changed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the company this snapshot belongs to
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_uuid', 'uuid');
    }

    /**
     * Get the user who created this snapshot
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }
}
