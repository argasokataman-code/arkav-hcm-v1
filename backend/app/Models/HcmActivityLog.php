<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class HcmActivityLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'uuid',
        'company_id',
        'entity_type',
        'entity_uuid',
        'action',
        'performed_by_uuid',
        'performed_by_email',
        'ip_address',
        'changed_fields',
        'meta',
        'occurred_at',
    ];

    protected $casts = [
        'changed_fields' => 'array',
        'meta' => 'array',
        'occurred_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }
}
