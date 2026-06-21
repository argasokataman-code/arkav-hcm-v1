<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DomainVerificationLog extends Model
{
    use AssignsUuid;

    protected $fillable = [
        'domain_id',
        'status',
        'verification_method',
        'details',
        'attempted_at',
    ];

    protected $casts = [
        'attempted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(CustomDomain::class);
    }
}
