<?php

namespace App\Services;

use App\Mail\InvoiceMailable;
use App\Models\Invoice;
use App\Models\CompanySetting;
use App\Support\WebsiteSettings;
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
        $context = $this->resolveInvoiceRenderContext($invoice);

        return View::make('pdf.invoice', [
            'invoice' => $invoice,
            'companyAddress' => config('hcm.organization_address'),
            'appName' => config('app.name'),
            'companyProfile' => $context['companyProfile'],
            'issuerProfile' => $context['issuerProfile'],
            'invoiceDisplaySettings' => $context['invoiceDisplaySettings'],
        ])->render();
    }

    /**
     * @return array{
     *   companyProfile: array<string, string|null>,
     *   issuerProfile: array<string, string|null>,
     *   invoiceDisplaySettings: array<string, string|bool|null>
     * }
     */
    public function resolveInvoiceRenderContext(Invoice $invoice): array
    {
        $invoice->loadMissing('company');

        $companyProfile = $this->resolveInvoiceCompanyProfile($invoice);

        return [
            'companyProfile' => $companyProfile,
            'issuerProfile' => $this->resolveIssuerProfile(),
            'invoiceDisplaySettings' => $this->resolveInvoiceDisplaySettings((int) ($invoice->company_id ?? 0)),
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function resolveInvoiceCompanyProfile(Invoice $invoice): array
    {
        $company = $invoice->company;
        if (! $company) {
            return [
                'name' => null,
                'legalName' => null,
                'address' => null,
                'city' => null,
                'state' => null,
                'country' => null,
                'postalCode' => null,
            ];
        }

        $settings = CompanySetting::query()
            ->where('company_id', $company->id)
            ->whereIn('key', [
                'company_profile_address',
                'company_profile_city',
                'company_profile_state',
                'company_profile_country',
                'company_profile_postal_code',
            ])
            ->pluck('value', 'key');

        return [
            'name' => $company->name,
            'legalName' => $company->legal_name,
            'address' => $settings->get('company_profile_address'),
            'city' => $settings->get('company_profile_city'),
            'state' => $settings->get('company_profile_state'),
            'country' => $settings->get('company_profile_country'),
            'postalCode' => $settings->get('company_profile_postal_code'),
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function resolveIssuerProfile(): array
    {
        $businessSettings = WebsiteSettings::allBusinessSettings();

        return [
            'name' => $businessSettings['business_company_name'] ?: (string) config('app.name', 'Arkav'),
            'email' => $businessSettings['business_email'] ?: null,
            'phone' => $businessSettings['business_phone'] ?: null,
            'fax' => $businessSettings['business_fax'] ?: null,
            'website' => $businessSettings['business_website'] ?: null,
            'address' => $businessSettings['business_address'] ?: null,
            'city' => $businessSettings['business_city'] ?: null,
            'state' => $businessSettings['business_state'] ?: null,
            'country' => $businessSettings['business_country'] ?: null,
            'postalCode' => $businessSettings['business_postal_code'] ?: null,
        ];
    }

    /**
     * @return array<string, string|bool|null>
     */
    private function resolveInvoiceDisplaySettings(int $companyId): array
    {
        if ($companyId <= 0) {
            return [
                'invoice_prefix' => WebsiteSettings::prefixInvoice(),
                'invoice_due_days' => '30',
                'invoice_round_off' => 'none',
                'invoice_round_off_enabled' => false,
                'invoice_show_tax' => true,
                'invoice_header_terms' => null,
                'invoice_footer_terms' => null,
            ];
        }

        $settings = CompanySetting::query()
            ->where('company_id', $companyId)
            ->whereIn('key', [
                'invoice_prefix',
                'invoice_due_days',
                'invoice_round_off',
                'invoice_round_off_enabled',
                'invoice_show_tax',
                'invoice_header_terms',
                'invoice_footer_terms',
            ])
            ->pluck('value', 'key');

        return [
            'invoice_prefix' => $settings->get('invoice_prefix') ?: WebsiteSettings::prefixInvoice(),
            'invoice_due_days' => $settings->get('invoice_due_days') ?: '30',
            'invoice_round_off' => $settings->get('invoice_round_off') ?: 'none',
            'invoice_round_off_enabled' => (string) ($settings->get('invoice_round_off_enabled') ?? '0') === '1',
            'invoice_show_tax' => (string) ($settings->get('invoice_show_tax') ?? '1') !== '0',
            'invoice_header_terms' => $settings->get('invoice_header_terms') ?: null,
            'invoice_footer_terms' => $settings->get('invoice_footer_terms') ?: null,
        ];
    }
}
