<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WilayahProvince extends Model
{
    protected $fillable = [
        'code',
        'name',
    ];

    public function regencies(): HasMany
    {
        return $this->hasMany(WilayahRegency::class, 'province_id');
    }
}