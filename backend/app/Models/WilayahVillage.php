<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WilayahVillage extends Model
{
    use AssignsUuid;
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