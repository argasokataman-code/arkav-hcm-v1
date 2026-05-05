<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HcmSptMasaHeader extends Model
{
    use AssignsUuid, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_READY = 'ready';

    public const STATUS_SUBMITTED = 'submitted';

    /** Statuses that count as "active" — only one allowed per (company_id, periode). */
    public const ACTIVE_STATUSES = [self::STATUS_DRAFT, self::STATUS_READY];

    protected $fillable = [
        'company_id',
        'company_uuid',
        'periode',
        'status',
        'total_bruto',
        'total_pph21',
        'total_karyawan',
        'version',
        'generation_key',
        'generated_at',
        'submitted_at',
        'notes',
        'created_by_user_id',
        'created_by_user_uuid',
        'submitted_by_user_id',
        'submitted_by_user_uuid',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'total_bruto' => 'decimal:2',
            'total_pph21' => 'decimal:2',
            'total_karyawan' => 'integer',
            'version' => 'integer',
            'generated_at' => 'datetime',
            'submitted_at' => 'datetime',
            'created_by_user_id' => 'integer',
            'submitted_by_user_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $model): void {
            if (! $model->company_uuid && $model->company_id) {
                $model->company_uuid = Company::query()
                    ->where('id', (int) $model->company_id)
                    ->value('uuid');
            }
            if (! $model->created_by_user_uuid && $model->created_by_user_id) {
                $model->created_by_user_uuid = User::query()
                    ->where('id', (int) $model->created_by_user_id)
                    ->value('uuid');
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(HcmSptMasaDetail::class, 'hcm_spt_masa_header_id');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY;
    }

    public function isSubmitted(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }
}
