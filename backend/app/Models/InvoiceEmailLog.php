<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceEmailLog extends Model
{
    use HasFactory, AssignsUuid;

    protected $fillable = [
        'invoice_id',
        'to_email',
        'event_key',
        'status',
        'provider_message_id',
        'error_message',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}

