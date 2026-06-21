<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;

class PerformanceCycle extends Model
{
    use AssignsUuid;

    protected $fillable = [
        'name',
        'period_start',
        'period_end',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
        ];
    }
}
