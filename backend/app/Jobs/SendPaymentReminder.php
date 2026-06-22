<?php

namespace App\Jobs;

use App\Mail\PaymentReminderMailable;
use App\Models\CompanyUser;
use App\Models\Invoice;
use App\Models\InvoiceEmailLog;
use App\Services\NotificationDeliveryRecorder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendPaymentReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(): void
    {
        $deliveryRecorder = app(NotificationDeliveryRecorder::class);

        // Find invoices due soon (within 7 days) or overdue
        $invoices = Invoice::query()
            ->select(['id', 'company_id', 'uuid', 'invoice_number', 'due_date', 'is_paid', 'amount_due', 'status', 'subscription_id', 'notes'])
            ->with([
                'company' => fn ($q) => $q->select(['id', 'uuid', 'name', 'owner_user_id']),
                'company.owner' => fn ($q) => $q->select(['id', 'email', 'name']),
            ])
            ->where('is_paid', false)
            ->where(function ($query) {
                $query->whereBetween('due_date', [now(), now()->addDays(7)])
                    ->orWhere('due_date', '<', now());
            })
            ->get();

        foreach ($invoices as $invoice) {
            try {
                $email = $this->resolveRecipientEmail($invoice);

                if ($email !== '') {
                    Mail::to($email)
                        ->send(new PaymentReminderMailable($invoice));

                    InvoiceEmailLog::query()->create([
                        'invoice_id' => $invoice->id,
                        'to_email' => $email,
                        'event_key' => 'billing.invoice.reminder_sent',
                        'status' => 'sent',
                        'provider_message_id' => null,
                        'error_message' => null,
                    ]);

                    // Log reminder sent
                    \Log::info("Payment reminder sent for invoice {$invoice->invoice_number}");

                    $deliveryRecorder->recordSent('billing.invoice.reminder_sent', 'mail', [
                        'recipient' => $email,
                        'companyUuid' => (string) ($invoice->company?->uuid ?? ''),
                        'attemptCount' => method_exists($this, 'attempts') ? (int) $this->attempts() : 1,
                        'metadata' => [
                            'source' => 'send-payment-reminder.job',
                            'invoiceUuid' => (string) ($invoice->uuid ?? ''),
                            'invoiceNumber' => (string) ($invoice->invoice_number ?? ''),
                        ],
                    ]);
                } else {
                    $deliveryRecorder->recordDropped('billing.invoice.reminder_failed', 'mail', [
                        'recipient' => '',
                        'companyUuid' => (string) ($invoice->company?->uuid ?? ''),
                        'attemptCount' => method_exists($this, 'attempts') ? (int) $this->attempts() : 1,
                        'lastError' => 'Reminder recipient email not configured.',
                        'metadata' => [
                            'source' => 'send-payment-reminder.job',
                            'invoiceUuid' => (string) ($invoice->uuid ?? ''),
                            'invoiceNumber' => (string) ($invoice->invoice_number ?? ''),
                            'dropReason' => 'recipient_email_missing',
                        ],
                    ]);
                }
            } catch (\Exception $e) {
                InvoiceEmailLog::query()->create([
                    'invoice_id' => $invoice->id,
                    'to_email' => $this->resolveRecipientEmail($invoice),
                    'event_key' => 'billing.invoice.reminder_failed',
                    'status' => 'failed',
                    'provider_message_id' => null,
                    'error_message' => $e->getMessage(),
                ]);

                \Log::error("Failed to send payment reminder for invoice {$invoice->id}", [
                    'error' => $e->getMessage(),
                ]);

                $deliveryRecorder->recordFailed('billing.invoice.reminder_failed', 'mail', [
                    'recipient' => $this->resolveRecipientEmail($invoice),
                    'companyUuid' => (string) ($invoice->company?->uuid ?? ''),
                    'attemptCount' => method_exists($this, 'attempts') ? (int) $this->attempts() : 1,
                    'lastError' => $e->getMessage(),
                    'metadata' => [
                        'source' => 'send-payment-reminder.job',
                        'invoiceUuid' => (string) ($invoice->uuid ?? ''),
                        'invoiceNumber' => (string) ($invoice->invoice_number ?? ''),
                    ],
                ]);
            }
        }
    }

    private function resolveRecipientEmail(Invoice $invoice): string
    {
        $ownerEmail = trim((string) ($invoice->company?->owner?->email ?? ''));
        if ($ownerEmail !== '') {
            return $ownerEmail;
        }

        $fallbackUser = CompanyUser::query()
            ->with('user')
            ->where('company_id', $invoice->company_id)
            ->where('status', 'active')
            ->whereIn('role', ['owner', 'admin'])
            ->orderByRaw("CASE WHEN role = 'owner' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->first();

        return trim((string) ($fallbackUser?->user?->email ?? ''));
    }
}
