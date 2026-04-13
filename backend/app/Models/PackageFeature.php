<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageFeature extends Model
{
    protected $fillable = [
        'package_id',
        'feature_code',
        'feature_name',
        'limit',
    ];

    protected $casts = [
        'limit' => 'integer',
    ];

    /**
     * Associated package
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * Check if feature is included (not limit = 0)
     */
    public function isIncluded(): bool
    {
        return $this->limit !== 0;
    }

    /**
     * Check if feature is unlimited
     */
    public function isUnlimited(): bool
    {
        return $this->limit === null;
    }
}
