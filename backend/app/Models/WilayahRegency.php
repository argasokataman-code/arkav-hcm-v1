<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WilayahRegency extends Model
{
    protected $fillable = [
        'province_id',
        'code',
        'name',
    ];

    protected function casts(): array
    {
        return [
            'province_id' => 'integer',
        ];
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(WilayahProvince::class, 'province_id');
    }

    public function districts(): HasMany
    {
        return $this->hasMany(WilayahDistrict::class, 'regency_id');
    }
}