<?php

namespace App\Services;

use App\Mail\InvoiceMailable;
use App\Models\Invoice;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;

class InvoiceService
{
    /**
     * Send invoice to client via email
     */
    public function sendInvoice(Invoice $invoice, ?string $email = null): bool
    {
        return $this->sendInvoiceWithResult($invoice, $email)['ok'] === true;
    }

    /**
     * Send invoice and return structured result for logging.
     *
     * @return array{ok: bool, toEmail: string|null, error: string|null}
     */
    public function sendInvoiceWithResult(Invoice $invoice, ?string $email = null): array
    {
        // NOTE: Company model in this repo does not have a canonical email column.
        // Prefer explicit email param; fallback to owner email if present.
        $invoice->loadMissing('company.owner');

        $toEmail = $email
            ?: ($invoice->company?->owner?->email ?? null);

        if (! $toEmail) {
            return ['ok' => false, 'toEmail' => null, 'error' => 'Missing recipient email.'];
        }

        try {
            Mail::to($toEmail)->send(new InvoiceMailable($invoice));

            $invoice->update(['status' => 'sent']);

            return ['ok' => true, 'toEmail' => $toEmail, 'error' => null];
        } catch (\Throwable $e) {
            \Log::error('Failed to send invoice '.$invoice->id, [
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'toEmail' => $toEmail, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send bulk invoices
     */
    public function sendBulkInvoices(array $invoiceIds): array
    {
        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($invoiceIds as $invoiceId) {
            $invoice = Invoice::find($invoiceId);

            if (!$invoice) {
                $results['failed']++;
                $results['errors'][] = "Invoice $invoiceId not found";
                continue;
            }

            if ($this->sendInvoice($invoice)) {
                $results['success']++;
            } else {
                $results['failed']++;
                $results['errors'][] = "Failed to send invoice {$invoice->invoice_number}";
            }
        }

        return $results;
    }

    /**
     * Generate invoice PDF (placeholder - implement with dompdf)
     */
    public function generatePdf(Invoice $invoice): ?string
    {
        try {
            $filename = 'invoices/invoice-'.$invoice->id.'.pdf';
            $fullPath = storage_path('app/private/'.$filename);
            File::ensureDirectoryExists(dirname($fullPath));

            $invoice->loadMissing('company', 'purchaseTransaction');
            $html = $this->invoiceHtml($invoice);

            $options = new Options;
            $options->set('isRemoteEnabled', false);
            $options->set('defaultFont', 'DejaVu Sans');

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            File::put($fullPath, $dompdf->output());

            $invoice->update(['pdf_path' => $filename]);

            return $filename;
        } catch (\Exception $e) {
            \Log::error('Failed to generate PDF for invoice ' . $invoice->id, [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get formatted invoice data for display
     */
    public function formatInvoice(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'invoiceNumber' => $invoice->invoice_number,
            'company' => $invoice->company?->name,
            'companyId' => $invoice->company_id,
            'amountDue' => (float) $invoice->amount_due,
            'issueDate' => $invoice->issue_date?->toDateString(),
            'dueDate' => $invoice->due_date?->toDateString(),
            'status' => $invoice->status,
            'isPaid' => (bool) $invoice->is_paid,
            'paidDate' => $invoice->paid_date?->toDateString(),
            'isOverdue' => $invoice->is_paid ? false : ($invoice->due_date && $invoice->due_date->isPast()),
            'isDueSoon' => $invoice->is_paid ? false : ($invoice->due_date && $invoice->due_date->diffInDays(now()) <= 7),
            'notes' => $invoice->notes,
            'pdfPath' => $invoice->pdf_path,
            'createdAt' => $invoice->created_at?->toIso8601String(),
            'updatedAt' => $invoice->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Render invoice HTML for PDF generation.
     */
    private function invoiceHtml(Invoice $invoice): string
    {
        $companyName = e($invoice->company?->name ?? 'Unknown Company');
        $invoiceNumber = e($invoice->invoice_number);
        $issueDate = e(optional($invoice->issue_date)->toDateString() ?? '-');
        $dueDate = e(optional($invoice->due_date)->toDateString() ?? '-');
        $status = e($invoice->status);
        $amountDue = number_format((float) $invoice->amount_due, 2, ',', '.');
        $notes = nl2br(e((string) ($invoice->notes ?? '-')));

        return <<<HTML
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111827; }
        .wrap { padding: 24px; }
        .title { font-size: 22px; font-weight: 700; margin-bottom: 8px; }
        .meta { margin-bottom: 20px; }
        .box { border: 1px solid #d1d5db; border-radius: 8px; padding: 16px; margin-bottom: 16px; }
        .label { color: #6b7280; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; }
        .notes { white-space: pre-line; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 8px 0; border-bottom: 1px solid #e5e7eb; }
        td:first-child { width: 35%; color: #6b7280; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="title">Invoice {$invoiceNumber}</div>
        <div class="meta">{$companyName}</div>

        <div class="box">
            <table>
                <tr><td>Invoice Number</td><td>{$invoiceNumber}</td></tr>
                <tr><td>Company</td><td>{$companyName}</td></tr>
                <tr><td>Issue Date</td><td>{$issueDate}</td></tr>
                <tr><td>Due Date</td><td>{$dueDate}</td></tr>
                <tr><td>Status</td><td>{$status}</td></tr>
                <tr><td>Amount Due</td><td>IDR {$amountDue}</td></tr>
            </table>
        </div>

        <div class="box">
            <div class="label">Notes</div>
            <div class="notes">{$notes}</div>
        </div>
    </div>
</body>
</html>
HTML;
    }
}
