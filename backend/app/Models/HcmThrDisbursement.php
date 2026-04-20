<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HcmThrDisbursement extends Model
{
    use AssignsUuid;
    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';


    protected $fillable = [
        'hcm_thr_batch_id',
        'status',
        'driver',
        'meta',
        'initiated_by_user_id',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(HcmThrBatch::class, 'hcm_thr_batch_id');
    }
}
