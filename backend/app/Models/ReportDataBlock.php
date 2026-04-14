<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportDataBlock extends Model
{
    protected $fillable = [
        'snapshot_id',
        'module',
        'data_key',
        'data_value',
    ];

    protected function casts(): array
    {
        return [
            'data_value' => 'array',
        ];
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(ReportSnapshot::class, 'snapshot_id');
    }
}
