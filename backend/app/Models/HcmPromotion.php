<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HcmPromotion extends Model
{
    use AssignsUuid;

    protected $table = 'hcm_promotions';

    protected $fillable = [
        'company_id',
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

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
