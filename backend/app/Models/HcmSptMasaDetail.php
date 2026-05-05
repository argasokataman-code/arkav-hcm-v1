<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HcmSptMasaDetail extends Model
{
    use AssignsUuid;

    public const EMPLOYMENT_PERMANENT = 'permanent';

    public const EMPLOYMENT_CONTRACT = 'contract';

    public const EMPLOYMENT_INTERN = 'intern';

    public const EMPLOYMENT_NON_EMPLOYEE = 'non_employee';

    public const KATEGORI_PEGAWAI_TETAP = 'pegawai_tetap';

    public const KATEGORI_TIDAK_TETAP = 'tidak_tetap';

    public const KATEGORI_NON_PEGAWAI = 'non_pegawai';

    protected $fillable = [
        'hcm_spt_masa_header_id',
        'hcm_spt_masa_header_uuid',
        'company_id',
        'user_id',
        'user_uuid',
        'nama',
        'npwp',
        'nik',
        'employment_type',
        'kategori_spt',
        'bruto',
        'pph21',
        'bukti_potong_type',
    ];

    protected function casts(): array
    {
        return [
            'hcm_spt_masa_header_id' => 'integer',
            'company_id' => 'integer',
            'user_id' => 'integer',
            'bruto' => 'decimal:2',
            'pph21' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $model): void {
            if (! $model->hcm_spt_masa_header_uuid && $model->hcm_spt_masa_header_id) {
                $model->hcm_spt_masa_header_uuid = HcmSptMasaHeader::query()
                    ->where('id', (int) $model->hcm_spt_masa_header_id)
                    ->value('uuid');
            }
            if (! $model->user_uuid && $model->user_id) {
                $model->user_uuid = User::query()
                    ->where('id', (int) $model->user_id)
                    ->value('uuid');
            }
        });
    }

    public function header(): BelongsTo
    {
        return $this->belongsTo(HcmSptMasaHeader::class, 'hcm_spt_masa_header_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
