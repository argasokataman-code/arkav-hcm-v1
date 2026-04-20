<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class Package extends Model
{
    use AssignsUuid;
    use HasFactory;

    protected $primaryKey = 'uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'name',
        'description',
        'monthly_price',
        'yearly_price',
        'billing_unit',
        'status',
        'color',
        'sort_order',
    ];

    protected $casts = [
        'monthly_price' => 'decimal:2',
        'yearly_price' => 'decimal:2',
    ];


    /**
     * Package features
     */
    public function features(): HasMany
    {
        if (Schema::hasColumn('package_features', 'package_uuid')) {
            return $this->hasMany(PackageFeature::class, 'package_uuid', 'uuid');
        }

        return $this->hasMany(PackageFeature::class, 'package_id', 'id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'package_uuid', 'uuid');
    }

    /**
     * Scope for active packages
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Get feature by code
     */
    public function getFeature(string $code)
    {
        return $this->features()->where('feature_code', $code)->first();
    }

    /**
     * Check if package has feature
     */
    public function hasFeature(string $code): bool
    {
        return $this->features()
            ->where('feature_code', $code)
            ->where(function ($query): void {
                $query->whereNull('limit')
                    ->orWhere('limit', '!=', 0);
            })
            ->exists();
    }

    /**
     * Get feature limit
     */
    public function getFeatureLimit(string $code): ?int
    {
        $feature = $this->getFeature($code);
        return $feature?->limit;
    }
}
