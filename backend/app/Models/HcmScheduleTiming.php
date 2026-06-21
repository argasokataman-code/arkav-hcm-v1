<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HcmScheduleTiming extends Model
{
    use AssignsUuid;

    protected $fillable = [
        'company_id',
        'user_id',
        'hcm_shift_id',
        'start_time',
        'end_time',
        'source',
        'updated_by_user_id',
    ];

    protected $casts = [
        'company_id' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(HcmShift::class, 'hcm_shift_id');
    }
}
