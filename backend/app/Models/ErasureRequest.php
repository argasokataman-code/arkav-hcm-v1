<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ErasureRequest extends Model
{
    use AssignsUuid;

    protected $fillable = [
        'uuid',
        'subject_uuid',
        'company_id',
        'status',
        'reason',
        'reviewed_by_uuid',
        'reviewed_at',
        'completed_at',
        'admin_notes',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
