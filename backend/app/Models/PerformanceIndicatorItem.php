<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PerformanceIndicatorItem extends Model
{
    use AssignsUuid;

    protected $fillable = [
        'template_id',
        'section',
        'title',
        'description',
        'weight',
        'rating_scale_min',
        'rating_scale_max',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
            'rating_scale_min' => 'integer',
            'rating_scale_max' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(PerformanceIndicatorTemplate::class, 'template_id');
    }
}

