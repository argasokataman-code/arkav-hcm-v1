<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;

class HcmEmployeeDocument extends Model
{
    use AssignsUuid, SoftDeletes;

    protected $table = 'hcm_employee_documents';

    protected static function booted(): void
    {
        static::saving(function (self $record): void {
            if (Schema::hasColumn($record->getTable(), 'company_uuid') && ! $record->company_uuid && $record->company_id) {
                $record->company_uuid = (string) (Company::query()->where('id', $record->company_id)->value('uuid') ?? '');
            }

            if (Schema::hasColumn($record->getTable(), 'employee_profile_uuid') && ! $record->employee_profile_uuid && $record->employee_profile_id) {
                $record->employee_profile_uuid = (string) (EmployeeProfile::query()->where('id', $record->employee_profile_id)->value('uuid') ?? '');
            }

            if (Schema::hasColumn($record->getTable(), 'category_uuid') && ! $record->category_uuid && $record->category_id) {
                $record->category_uuid = (string) (HcmEmployeeDocumentCategory::query()->where('id', $record->category_id)->value('uuid') ?? '');
            }

            if (Schema::hasColumn($record->getTable(), 'uploaded_by_uuid') && ! $record->uploaded_by_uuid && $record->uploaded_by) {
                $record->uploaded_by_uuid = (string) (User::query()->where('id', $record->uploaded_by)->value('uuid') ?? '');
            }
        });
    }

    protected $fillable = [
        'company_id',
        'company_uuid',
        'employee_profile_id',
        'employee_profile_uuid',
        'category_id',
        'category_uuid',
        'title',
        'description',
        'file_path',
        'original_name',
        'mime_type',
        'size_bytes',
        'disk',
        'visibility',
        'expires_at',
        'uploaded_by',
        'uploaded_by_uuid',
    ];

    protected $casts = [
        'expires_at' => 'date:Y-m-d',
        'size_bytes' => 'integer',
    ];

    public function employeeProfile(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class, 'employee_profile_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(HcmEmployeeDocumentCategory::class, 'category_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
