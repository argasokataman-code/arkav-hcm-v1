<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;

class DataBreachIncident extends Model
{
    use AssignsUuid;

    public const STATUS_DETECTED = 'detected';
    public const STATUS_NOTIFIED = 'notified';
    public const STATUS_RESOLVED = 'resolved';

    protected $fillable = [
        'uuid',
        'company_id',
        'title',
        'description',
        'affected_data_types',
        'affected_subjects_count',
        'affected_user_uuids',
        'detected_at',
        'reported_to_bssn_at',
        'notifications_sent_at',
        'status',
        'created_by_uuid',
    ];

    protected $casts = [
        'affected_data_types' => 'array',
        'affected_user_uuids' => 'array',
        'affected_subjects_count' => 'integer',
        'detected_at' => 'datetime',
        'reported_to_bssn_at' => 'datetime',
        'notifications_sent_at' => 'datetime',
    ];
}
