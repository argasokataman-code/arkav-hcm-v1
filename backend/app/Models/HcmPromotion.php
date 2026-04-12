<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HcmPromotion extends Model
{
    protected $table = 'hcm_promotions';

    protected $fillable = [
        'user_id',
        'department',
        'designation_from',
        'designation_to',
        'promotion_date',
        'notes',
    ];

    protected $casts = [
        'promotion_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

