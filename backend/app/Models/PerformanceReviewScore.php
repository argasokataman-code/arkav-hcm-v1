<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerformanceReviewScore extends Model
{
    protected $fillable = [
        'review_id',
        'item_id',
        'self_score',
        'manager_score',
        'final_score',
        'self_comment',
        'manager_comment',
        'final_comment',
    ];

    protected function casts(): array
    {
        return [
            'self_score' => 'decimal:2',
            'manager_score' => 'decimal:2',
            'final_score' => 'decimal:2',
        ];
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(PerformanceReview::class, 'review_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(PerformanceIndicatorItem::class, 'item_id');
    }
}

