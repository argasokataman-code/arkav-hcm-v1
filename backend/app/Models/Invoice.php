<?php

namespace App\Models;

use App\Services\SubscriptionActivationFromInvoiceService;
use App\Support\WebsiteSettings;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'company_id',
        'purchase_transaction_id',
        'subscription_id',
        'issue_date',
        'due_date',
        'amount_due',
        'is_paid',
        'paid_date',
        'pdf_path',
        'status',
        'notes',
    ];

    protected $casts = [
        'amount_due' => 'decimal:2',
        'is_paid' => 'boolean',
        'issue_date' => 'date',
        'due_date' => 'date',
        'paid_date' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->invoice_number)) {
                $model->invoice_number = self::generateInvoiceNumber();
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function purchaseTransaction(): BelongsTo
    {
        return $this->belongsTo(PurchaseTransaction::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function emailLogs(): HasMany
    {
        return $this->hasMany(InvoiceEmailLog::class);
    }

    public function latestEmailLog(): HasOne
    {
        return $this->hasOne(InvoiceEmailLog::class)->latestOfMany();
    }

    /**
     * Mark invoice as sent
     */
    public function markAsSent(): void
    {
        $this->update(['status' => 'sent']);
    }

    /**
     * Mark invoice as paid
     */
    public function markAsPaid(): void
    {
        $this->update([
            'is_paid' => true,
            'paid_date' => now(),
            'status' => 'paid',
        ]);

        app(SubscriptionActivationFromInvoiceService::class)
            ->activateIfEligible($this->fresh());
    }

    /**
     * Check if invoice is overdue
     */
    public function isOverdue(): bool
    {
        return !$this->is_paid && $this->due_date?->isPast();
    }

    /**
     * Check if payment is due soon (within 7 days)
     */
    public function isDueSoon(): bool
    {
        return !$this->is_paid && $this->due_date?->isBetween(now(), now()->addDays(7));
    }

    /**
     * Generate invoice number
     */
    public static function generateInvoiceNumber(): string
    {
        $year = date('Y');
        $month = date('m');
        $prefix = WebsiteSettings::prefixInvoice();

        $count = static::whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count() + 1;
        $sequenceNumber = str_pad($count, 4, '0', STR_PAD_LEFT);

        return "{$prefix}{$year}{$month}-{$sequenceNumber}";
    }
}
