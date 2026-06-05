<?php

namespace App\Models;

use App\Mail\PaymentSuccessMailable;
use App\Models\Concerns\AssignsUuid;
use App\Services\AddonRecurringSubscriptionService;
use App\Services\InvoiceService;
use App\Services\SubscriptionActivationFromInvoiceService;
use App\Support\WebsiteSettings;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class Invoice extends Model
{
    use HasFactory, AssignsUuid;

    protected $fillable = [
        'invoice_number',
        'company_id',
        'purchase_transaction_id',
        'subscription_id',
        'renewal_period_key',
        'issue_date',
        'due_date',
        'amount_due',
        'billing_tax_rate_snapshot',
        'is_paid',
        'paid_date',
        'pdf_path',
        'status',
        'renewal_reason_code',
        'renewal_reason_message',
        'notes',
    ];

    protected $casts = [
        'amount_due' => 'decimal:2',
        'billing_tax_rate_snapshot' => 'decimal:2',
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
        if ((bool) $this->is_paid) {
            return;
        }

        $this->update([
            'is_paid' => true,
            'paid_date' => now(),
            'status' => 'paid',
        ]);

        $transaction = $this->purchaseTransaction()->first();
        if ($transaction && (string) ($transaction->transaction_type ?? '') === 'addon' && (string) ($transaction->status ?? '') !== 'paid') {
            $transaction->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);
            app(AddonRecurringSubscriptionService::class)
                ->applyFromTransaction($transaction->fresh());
        }

        app(SubscriptionActivationFromInvoiceService::class)
            ->activateIfEligible($this->fresh());

        $this->sendPaymentSuccessEmail();
    }

    private function sendPaymentSuccessEmail(): void
    {
        $invoice = $this->fresh(['company.owner']);
        $recipient = (string) ($invoice->company?->owner?->email ?? '');
        if ($recipient === '' || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        // Generate PDF first so it can be attached to the email.
        if (! $invoice->pdf_path) {
            try {
                app(InvoiceService::class)->generatePdf($invoice);
                $invoice->refresh();
            } catch (\Throwable $e) {
                Log::warning('Failed to generate invoice PDF before payment success email.', [
                    'invoice_id' => $invoice->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        try {
            Mail::to($recipient)->send(new PaymentSuccessMailable($invoice));
        } catch (\Throwable $e) {
            Log::warning('Failed to send payment success email.', [
                'invoice_id' => $invoice->id,
                'company_id' => $invoice->company_id,
                'recipient' => $recipient,
                'error' => $e->getMessage(),
            ]);
        }
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

        $base = static::whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count() + 1;

        // Loop until a unique invoice number is found (handles race conditions / gaps)
        for ($i = 0; $i < 100; $i++) {
            $candidate = "{$prefix}{$year}{$month}-" . str_pad($base + $i, 4, '0', STR_PAD_LEFT);
            if (! static::where('invoice_number', $candidate)->exists()) {
                return $candidate;
            }
        }

        // Last-resort fallback: timestamp suffix guarantees uniqueness
        return "{$prefix}{$year}{$month}-" . str_pad($base, 4, '0', STR_PAD_LEFT) . '-' . substr((string) time(), -4);
    }
}
