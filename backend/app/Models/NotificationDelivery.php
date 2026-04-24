<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationDelivery extends Model
{
    use AssignsUuid, HasFactory;

    protected $fillable = [
        'event_key',
        'channel',
        'status',
        'notification_uuid',
        'recipient',
        'company_uuid',
        'attempt_count',
        'last_error',
        'metadata',
        'sent_at',
        'failed_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
    ];
}
