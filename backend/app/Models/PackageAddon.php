<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PackageAddon extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $record): void {
            if (empty($record->uuid)) {
                $record->uuid = (string) Str::uuid();
            }
        });
    }

    protected $fillable = [
        'code',
        'name',
        'description',
        'price_per_unit',
        'unit_name',
        'status',
    ];

    protected $casts = [
        'price_per_unit' => 'decimal:2',
    ];

    /**
     * Scope for active addons
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PurchaseTransaction::class, 'package_addon_id');
    }
}
