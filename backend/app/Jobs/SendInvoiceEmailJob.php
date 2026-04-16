<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Models\InvoiceEmailLog;
use App\Services\InvoiceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendInvoiceEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
            'status' => $result['ok'] ? 'sent' : 'failed',
            'provider_message_id' => null,
            'error_message' => $result['error'],
        ]);
    }
}

