<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class HcmTrainingType extends Model
{
    use AssignsUuid;

    protected static function booted(): void
    {
        static::saving(function (self $record): void {
            if (Schema::hasColumn($record->getTable(), 'company_uuid') && ! $record->company_uuid && $record->company_id) {
                $record->company_uuid = (string) (Company::query()->where('id', $record->company_id)->value('uuid') ?? '');
            }
        });
    }

    protected $fillable = [
        'company_id',
        'company_uuid',
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'company_uuid' => 'string',
        'is_active' => 'boolean',
    ];
}
