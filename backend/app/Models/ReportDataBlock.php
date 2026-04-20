<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ReportDataBlock extends Model
{
    use AssignsUuid;

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
