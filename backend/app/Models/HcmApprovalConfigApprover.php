<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HcmApprovalConfigApprover extends Model
{
    use AssignsUuid;

    protected $fillable = [
        'hcm_approval_config_id',
        'company_id',
        'approver_user_id',
        'approver_user_uuid',
        'sequence_order',
    ];

    protected function casts(): array
    {
        return [
            'hcm_approval_config_id' => 'integer',
            'company_id' => 'integer',
            'approver_user_id' => 'integer',
            'sequence_order' => 'integer',
        ];
    }

    public function config(): BelongsTo
    {
        return $this->belongsTo(HcmApprovalConfig::class, 'hcm_approval_config_id');
    }

    public function approverUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }
}
