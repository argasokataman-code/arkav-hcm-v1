<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HcmApprovalConfig extends Model
{
    use AssignsUuid;

    protected $fillable = [
        'company_id',
        'module',
        'approval_mode',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function approvers(): HasMany
    {
        return $this->hasMany(HcmApprovalConfigApprover::class)->orderBy('sequence_order');
    }
}
