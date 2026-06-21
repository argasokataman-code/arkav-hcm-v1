<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Models\InvoiceEmailLog;
use App\Services\InvoiceService;
use App\Services\NotificationDeliveryRecorder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendInvoiceEmailJob implements ShouldQueue
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

    public function __construct(
        public readonly int $invoiceId,
        public readonly ?string $toEmail = null,
    ) {}

    public function handle(): void
    {
        $invoice = Invoice::query()->with('company.owner')->find($this->invoiceId);
        if (! $invoice) {
            return;
        }

        $service = app(InvoiceService::class);
        $result = $service->sendInvoiceWithResult($invoice, $this->toEmail);

        InvoiceEmailLog::query()->create([
            'invoice_id' => $invoice->id,
            'to_email' => (string) ($result['toEmail'] ?? $this->toEmail ?? ''),
            'event_key' => $result['ok'] ? 'billing.invoice.email_sent' : 'billing.invoice.email_failed',
            'status' => $result['ok'] ? 'sent' : 'failed',
            'provider_message_id' => null,
            'error_message' => $result['error'],
        ]);

        $eventKey = $result['ok'] ? 'billing.invoice.email_sent' : 'billing.invoice.email_failed';
        $context = [
            'recipient' => (string) ($result['toEmail'] ?? $this->toEmail ?? ''),
            'companyUuid' => (string) ($invoice->company?->uuid ?? ''),
            'attemptCount' => method_exists($this, 'attempts') ? (int) $this->attempts() : 1,
            'lastError' => $result['error'] ?? null,
            'metadata' => [
                'source' => 'send-invoice-email.job',
                'invoiceUuid' => (string) ($invoice->uuid ?? ''),
                'invoiceNumber' => (string) ($invoice->invoice_number ?? ''),
            ],
        ];

        $deliveryRecorder = app(NotificationDeliveryRecorder::class);
        if ($result['ok']) {
            $deliveryRecorder->recordSent($eventKey, 'mail', $context);
        } else {
            $deliveryRecorder->recordFailed($eventKey, 'mail', $context);
        }
    }
}
