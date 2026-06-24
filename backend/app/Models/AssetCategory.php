<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Schema;

class AssetCategory extends Model
{
    use AssignsUuid, HasFactory;

    protected static function booted(): void
    {
        static::saving(function (self $record): void {
            if (Schema::hasColumn($record->getTable(), 'company_uuid') && ! $record->company_uuid && $record->company_id) {
                $record->company_uuid = (string) (Company::query()->where('id', $record->company_id)->value('uuid') ?? '');
            }
        });
    }

    protected $fillable = [
        'uuid',
        'company_id',
        'company_uuid',
        'code',
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'uuid' => 'string',
        'company_uuid' => 'string',
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
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

        throw (new ModelNotFoundException)->setModel(static::class, [$value]);
    }
}
