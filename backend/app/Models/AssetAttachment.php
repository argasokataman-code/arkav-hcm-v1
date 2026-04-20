<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class AssetAttachment extends Model
{
    use HasFactory, AssignsUuid;

    protected static function booted(): void
    {
        static::saving(function (self $record): void {
            if (Schema::hasColumn($record->getTable(), 'company_uuid') && ! $record->company_uuid && $record->company_id) {
                $record->company_uuid = (string) (Company::query()->where('id', $record->company_id)->value('uuid') ?? '');
            }

            if (Schema::hasColumn($record->getTable(), 'asset_uuid') && ! $record->asset_uuid && $record->asset_id) {
                $record->asset_uuid = (string) (Asset::query()->where('id', $record->asset_id)->value('uuid') ?? '');
            }

            if (Schema::hasColumn($record->getTable(), 'uploaded_by_uuid') && ! $record->uploaded_by_uuid && $record->uploaded_by) {
                $record->uploaded_by_uuid = (string) (User::query()->where('id', $record->uploaded_by)->value('uuid') ?? '');
            }
        });
    }

    protected $fillable = [
        'uuid',
        'company_id',
        'company_uuid',
        'asset_id',
        'asset_uuid',
        'file_path',
        'file_type',
        'disk',
        'original_name',
        'size_bytes',
        'uploaded_by',
        'uploaded_by_uuid',
    ];

    protected $casts = [
        'uuid' => 'string',
        'company_uuid' => 'string',
        'asset_uuid' => 'string',
        'uploaded_by_uuid' => 'string',
        'size_bytes' => 'integer',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}