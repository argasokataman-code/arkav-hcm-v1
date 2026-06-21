<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Domain extends Model
{
    use AssignsUuid, HasFactory;

    protected $fillable = [
        'domain_name',
        'company_id',
        'verification_type',
        'status',
        'verification_token',
        'verification_data',
        'verified_at',
        'notes',
    ];

    protected $casts = [
        'verification_data' => 'array',
        'verified_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
