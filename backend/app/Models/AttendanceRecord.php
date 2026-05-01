<?php
namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class AttendanceRecord extends Model
{
    use AssignsUuid;

    protected static function booted(): void
    {
        static::saving(function (self $record): void {
            if (Schema::hasColumn($record->getTable(), 'company_uuid') && ! $record->company_uuid && $record->company_id) {
                $record->company_uuid = (string) (Company::query()->where('id', $record->company_id)->value('uuid') ?? '');
            }

            if (Schema::hasColumn($record->getTable(), 'user_uuid') && ! $record->user_uuid && $record->user_id) {
                $record->user_uuid = (string) (User::query()->where('id', $record->user_id)->value('uuid') ?? '');
            }

            if (Schema::hasColumn($record->getTable(), 'corrected_by_user_uuid') && ! $record->corrected_by_user_uuid && $record->corrected_by_user_id) {
                $record->corrected_by_user_uuid = (string) (User::query()->where('id', $record->corrected_by_user_id)->value('uuid') ?? '');
            }

            if ($record->status === 'on_leave') {
                $record->status = 'leave';
            }
        });
    }

    protected $fillable = [
        'uuid',
        'company_id',
        'company_uuid',
        'user_id',
        'user_uuid',
        'work_date',
        'status',
        'correction_status',
        'check_in_at',
        'check_in_latitude',
        'check_in_longitude',
        'check_in_location_name',
        'check_in_location_address',
        'check_in_location_source',
        'check_out_at',
        'check_out_latitude',
        'check_out_longitude',
        'check_out_location_name',
        'check_out_location_address',
        'check_out_location_source',
        'break_minutes',
        'break_started_at',
        'late_minutes',
        'correction_reason',
        'correction_requested_at',
        'corrected_by_user_id',
        'corrected_by_user_uuid',
        'corrected_at',
        'selfie_path',
        'selfie_encrypted_hash',
    ];

    protected function casts(): array
    {
        return [
            'uuid' => 'string',
            'work_date' => 'date',
            'company_id' => 'integer',
            'company_uuid' => 'string',
            'user_uuid' => 'string',
            'corrected_by_user_uuid' => 'string',
            'check_in_at' => 'datetime',
            'check_in_latitude' => 'float',
            'check_in_longitude' => 'float',
            'check_out_at' => 'datetime',
            'check_out_latitude' => 'float',
            'check_out_longitude' => 'float',
            'break_minutes' => 'integer',
            'break_started_at' => 'datetime',
            'late_minutes' => 'integer',
            'correction_requested_at' => 'datetime',
            'corrected_at' => 'datetime',
            'check_in_location_source' => 'string',
            'check_out_location_source' => 'string',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
