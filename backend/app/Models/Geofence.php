<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Geofence extends Model
{
    use AssignsUuid;

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
        throw (new ModelNotFoundException)->setModel(static::class, [$value]);
    }

    protected $fillable = [
        'uuid',
        'company_id',
        'name',
        'latitude',
        'longitude',
        'radius_meters',
        'is_active',
    ];

    protected $casts = [
        'uuid' => 'string',
        'latitude' => 'float',
        'longitude' => 'float',
        'radius_meters' => 'integer',
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
