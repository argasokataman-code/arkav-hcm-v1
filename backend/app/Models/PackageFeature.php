<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PackageFeature extends Model
{
    use AssignsUuid;

    protected $fillable = [
        'package_uuid',
        'feature_code',
        'feature_name',
        'limit',
        'tier',
    ];

    protected $casts = [
        'limit' => 'integer',
    ];

    /**
     * Associated package
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'package_uuid', 'uuid');
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

    public function resolveRouteBinding($value, $field = null): ?self
    {
        $routeField = $field ?? $this->getRouteKeyName();

        $model = $this->newQuery()->where($routeField, $value)->first();
        if ($model) {
            return $model;
        }

        if ($field === null && is_numeric($value)) {
            return $this->newQuery()->whereKey((int) $value)->first();
        }

        throw (new ModelNotFoundException())->setModel(static::class, [$value]);
    }
}
