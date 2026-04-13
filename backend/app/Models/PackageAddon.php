<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackageAddon extends Model
{
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
}
