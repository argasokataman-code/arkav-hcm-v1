<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionEvent extends Model
{
    use HasFactory, AssignsUuid;

    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'company_uuid',
        'subscription_id',
        'subscription_uuid',
        'invoice_id',
        'invoice_uuid',
        'payment_id',
        'payment_uuid',
        'renewal_period_key',
        'event_type',
        'reason_code',
        'reason_message',
        'payload',
        'occurred_at',
        'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'occurred_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
