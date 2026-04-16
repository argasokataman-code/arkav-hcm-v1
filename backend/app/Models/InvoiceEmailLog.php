<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceEmailLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'to_email',
        'status',
        'provider_message_id',
        'error_message',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}

