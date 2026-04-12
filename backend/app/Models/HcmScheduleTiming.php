<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HcmScheduleTiming extends Model
{
    protected $fillable = [
        'user_id',
        'hcm_shift_id',
        'start_time',
        'end_time',
        'source',
        'updated_by_user_id',
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
