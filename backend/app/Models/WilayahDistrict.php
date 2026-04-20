<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WilayahDistrict extends Model
{
    use AssignsUuid;
    protected $fillable = [
        'regency_id',
        'code',
        'name',
    ];

    protected function casts(): array
    {
        return [
            'regency_id' => 'integer',
        ];
    }

    public function regency(): BelongsTo
    {
        return $this->belongsTo(WilayahRegency::class, 'regency_id');
    }

    public function villages(): HasMany
    {
        return $this->hasMany(WilayahVillage::class, 'district_id');
    }
}