<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
    use HasFactory;

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
        return $this->hasMany(PackageFeature::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
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
