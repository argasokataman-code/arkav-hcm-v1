<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Subscription;
use Illuminate\Console\Command;

class HcmBackfillTrialInvoicesCommand extends Command
{
    protected $signature = 'hcm:billing-backfill-trial-invoices
        {--company-code= : Batasi ke satu company code}
        {--dry-run : Simulasi tanpa membuat invoice}';

    protected $description = 'Backfill draft invoices for legacy trial subscriptions that do not yet have an invoice';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $companyCode = trim((string) ($this->option('company-code') ?? ''));

        $query = Subscription::query()
            ->with('company')
            ->where('status', 'trial')
            ->orderBy('id');

        if ($companyCode !== '') {
            $query->whereHas('company', function ($companyQuery) use ($companyCode): void {
                $companyQuery->whereRaw('LOWER(code) = ?', [strtolower($companyCode)]);
            });
        }

        $subscriptions = $query->get();

        if ($subscriptions->isEmpty()) {
            $this->info('No trial subscriptions found for backfill.');
            return self::SUCCESS;
        }

        $created = 0;
        $skippedExisting = 0;

        foreach ($subscriptions as $subscription) {
            $existingInvoice = Invoice::query()
                ->where('company_id', $subscription->company_id)
                ->where('subscription_id', $subscription->id)
                ->exists();

            if ($existingInvoice) {
                $skippedExisting++;
                continue;
            }

            $amountDue = (float) ($subscription->amount ?? 0);
            if ($amountDue < 0) {
                $amountDue = 0;
            }

            $issueDate = now()->toDateString();
            $dueDate = $subscription->trial_ends_at
                ? $subscription->trial_ends_at->toDateString()
                : now()->addDays(7)->toDateString();

            if ($dryRun) {
                $this->line(sprintf(
                    '[dry-run] would create trial invoice for sub #%d (company_id=%d, due=%s, amount=%.2f)',
                    $subscription->id,
                    $subscription->company_id,
                    $dueDate,
                    $amountDue
                ));
                $created++;
                continue;
            }

            $invoice = Invoice::query()->create([
                'company_id' => $subscription->company_id,
                'subscription_id' => $subscription->id,
                'purchase_transaction_id' => null,
                'issue_date' => $issueDate,
                'due_date' => $dueDate,
                'amount_due' => $amountDue,
                'status' => 'draft',
                'notes' => 'Backfilled for legacy trial subscription visibility.',
            ]);

            $created++;
            $this->line(sprintf(
                'created invoice %s for trial sub #%d (company_id=%d)',
                (string) $invoice->invoice_number,
                $subscription->id,
                $subscription->company_id
            ));
        }

        $this->newLine();
        if ($dryRun) {
            $this->warn('Dry run completed. No invoice was persisted.');
        }

        $this->info(sprintf('Trial invoice backfill summary: created=%d, skipped_existing=%d', $created, $skippedExisting));

        return self::SUCCESS;
    }
}
