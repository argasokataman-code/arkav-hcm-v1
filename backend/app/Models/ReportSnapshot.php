<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ReportSnapshot extends Model
{
    use HasFactory, AssignsUuid;

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
