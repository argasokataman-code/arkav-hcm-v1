<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WilayahVillage extends Model
{
    protected $fillable = [
        'district_id',
        'code',
        'name',
    ];

    protected function casts(): array
    {
        return [
            'district_id' => 'integer',
        ];
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(WilayahDistrict::class, 'district_id');
    }
}