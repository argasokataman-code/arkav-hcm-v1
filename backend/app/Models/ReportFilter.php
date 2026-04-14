<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportFilter extends Model
{
    protected $fillable = [
        'snapshot_id',
        'filter_key',
        'filter_value',
    ];

    protected function casts(): array
    {
        return [
            'filter_value' => 'array',
        ];
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(ReportSnapshot::class, 'snapshot_id');
    }
}
