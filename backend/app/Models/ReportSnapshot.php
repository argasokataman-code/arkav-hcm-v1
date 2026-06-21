<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property Carbon|null $period_start
 * @property Carbon|null $period_end
 * @property Carbon|null $generated_at
 * @property array<string, mixed>|null $meta
 */
class ReportSnapshot extends Model
{
    use AssignsUuid, HasFactory;

    protected $fillable = [
        'company_id',
        'report_type',
        'period_start',
        'period_end',
        'generated_at',
        'generated_by_user_id',
        'status',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'generated_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by_user_id');
    }

    public function dataBlocks(): HasMany
    {
        return $this->hasMany(ReportDataBlock::class, 'snapshot_id');
    }

    public function filters(): HasMany
    {
        return $this->hasMany(ReportFilter::class, 'snapshot_id');
    }

    public function exports(): HasMany
    {
        return $this->hasMany(ReportExport::class, 'snapshot_id');
    }
}
