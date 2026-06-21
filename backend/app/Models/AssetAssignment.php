<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class AssetAssignment extends Model
{
    use AssignsUuid, HasFactory;

    protected static function booted(): void
    {
        static::saving(function (self $record): void {
            if (Schema::hasColumn($record->getTable(), 'company_uuid') && ! $record->company_uuid && $record->company_id) {
                $record->company_uuid = (string) (Company::query()->where('id', $record->company_id)->value('uuid') ?? '');
            }

            if (Schema::hasColumn($record->getTable(), 'asset_uuid') && ! $record->asset_uuid && $record->asset_id) {
                $record->asset_uuid = (string) (Asset::query()->where('id', $record->asset_id)->value('uuid') ?? '');
            }

            if (Schema::hasColumn($record->getTable(), 'employee_uuid') && ! $record->employee_uuid && $record->employee_id) {
                $record->employee_uuid = (string) (EmployeeProfile::query()->where('id', $record->employee_id)->value('uuid') ?? '');
            }
        });
    }

    protected $fillable = [
        'uuid',
        'company_id',
        'company_uuid',
        'asset_id',
        'asset_uuid',
        'employee_id',
        'employee_uuid',
        'assigned_date',
        'returned_date',
        'condition_at_assign',
        'condition_at_return',
        'active_token',
        'notes',
    ];

    protected $casts = [
        'uuid' => 'string',
        'company_uuid' => 'string',
        'asset_uuid' => 'string',
        'employee_uuid' => 'string',
        'assigned_date' => 'datetime',
        'returned_date' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function employeeProfile(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class, 'employee_id');
    }
}
