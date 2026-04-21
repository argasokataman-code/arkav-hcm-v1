<?php

namespace App\Services;

use App\Mail\InvoiceMailable;
use App\Models\Invoice;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
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

        // Ensure PDF is available so email can include attachment.
        if (! $invoice->pdf_path) {
            $generatedPath = $this->generatePdf($invoice);
            if (! $generatedPath) {
                return ['ok' => false, 'toEmail' => $toEmail, 'error' => 'Failed to generate invoice PDF.'];
            }

            $invoice->refresh();
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
        $invoice->loadMissing(['company:id,name,code', 'subscription.package']);

        $subscription = $invoice->subscription;
        $package = $subscription?->package;
        $packageCode = $package?->code ?? $subscription?->plan_code;
        $packageName = $package?->name ?? ($packageCode ? Str::headline((string) $packageCode) : null);
        $billingCycle = $subscription?->billing_cycle;
        $billingCycleLabel = match ($billingCycle) {
            'monthly' => 'Bulanan',
            'yearly' => 'Tahunan',
            default => null,
        };
        $nextBillingAt = $subscription?->status === 'trial'
            ? $subscription?->trial_ends_at
            : $subscription?->ends_at;

        return [
            'id' => $invoice->id,
            'invoiceNumber' => $invoice->invoice_number,
            'company' => $invoice->company?->name,
            'companyId' => $invoice->company_id,
            'subscriptionId' => $invoice->subscription_id,
            'packageCode' => $packageCode,
            'packageName' => $packageName,
            'packageDisplay' => collect([$packageName, $billingCycleLabel])->filter()->implode(' - '),
            'billingCycle' => $billingCycle,
            'billingCycleLabel' => $billingCycleLabel,
            'currentPeriodStart' => $subscription?->starts_at?->toDateString(),
            'currentPeriodEnd' => $subscription?->ends_at?->toDateString(),
            'nextBillingDate' => $nextBillingAt?->toDateString(),
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
        $invoice->loadMissing('company', 'purchaseTransaction', 'subscription');

        return View::make('pdf.invoice', [
            'invoice' => $invoice,
            'companyAddress' => config('hcm.organization_address'),
            'appName' => config('app.name'),
        ])->render();
    }
}
