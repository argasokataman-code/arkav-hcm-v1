<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;

class Asset extends Model
{
    use AssignsUuid, HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::saving(function (self $record): void {
            if (Schema::hasColumn($record->getTable(), 'company_uuid') && ! $record->company_uuid && $record->company_id) {
                $record->company_uuid = (string) (Company::query()->where('id', $record->company_id)->value('uuid') ?? '');
            }

            if (Schema::hasColumn($record->getTable(), 'asset_category_uuid') && ! $record->asset_category_uuid && $record->asset_category_id) {
                $record->asset_category_uuid = (string) (AssetCategory::query()->where('id', $record->asset_category_id)->value('uuid') ?? '');
            }
        });
    }

    protected $fillable = [
        'uuid',
        'company_id',
        'company_uuid',
        'asset_category_id',
        'asset_category_uuid',
        'asset_code',
        'name',
        'brand',
        'model',
        'serial_number',
        'purchase_date',
        'purchase_price',
        'condition',
        'status',
        'location',
        'notes',
        'warranty_start_date',
        'warranty_end_date',
    ];

    protected $casts = [
        'uuid' => 'string',
        'company_uuid' => 'string',
        'asset_category_uuid' => 'string',
        'purchase_date' => 'date',
        'purchase_price' => 'decimal:2',
        'warranty_start_date' => 'date',
        'warranty_end_date' => 'date',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(AssetAssignment::class);
    }

    public function currentAssignment(): HasOne
    {
        return $this->hasOne(AssetAssignment::class)->where('active_token', 'active');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(AssetLog::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(AssetAttachment::class);
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
