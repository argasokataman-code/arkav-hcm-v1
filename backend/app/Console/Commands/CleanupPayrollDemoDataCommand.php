<?php

namespace App\Console\Commands;

use App\Models\HcmPayrollLine;
use App\Models\HcmPayrollRun;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CleanupPayrollDemoDataCommand extends Command
{
    protected $signature = 'payroll:cleanup-demo-data {--dry-run : Simulasi tanpa menyimpan perubahan}';

    protected $description = 'Bersihkan dan normalisasi data payroll demo existing tanpa membuat data baru';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info('=== CLEANUP PAYROLL DEMO DATA ===');
        $this->line($dryRun ? 'Mode: DRY RUN (tidak ada perubahan disimpan)' : 'Mode: APPLY (perubahan disimpan)');

        $stats = [
            'runs_scanned' => 0,
            'runs_updated' => 0,
            'run_finalized_at_filled' => 0,
            'lines_scanned' => 0,
            'lines_updated' => 0,
            'lines_deleted_duplicates' => 0,
            'line_sort_resequenced' => 0,
            'line_paymentstatus_filled_paid' => 0,
            'line_paymentstatus_normalized' => 0,
            'line_meta_standardized' => 0,
            'line_kind_normalized' => 0,
        ];

        $runner = function () use (&$stats): void {
            $runs = HcmPayrollRun::query()
                ->with([
                    'period:id,period_year,period_month',
                    'lines' => function ($q) {
                        $q->with('user:id,name')->orderBy('user_id')->orderBy('sort_order')->orderBy('id');
                    },
                ])
                ->whereIn('purpose', [HcmPayrollRun::PURPOSE_MONTHLY, null])
                ->orderBy('id')
                ->get();

            foreach ($runs as $run) {
                $stats['runs_scanned']++;
                $runChanged = false;

                if (empty($run->purpose)) {
                    $run->purpose = HcmPayrollRun::PURPOSE_MONTHLY;
                    $runChanged = true;
                }

                if ($run->status === HcmPayrollRun::STATUS_FINALIZED && $run->finalized_at === null) {
                    $run->finalized_at = $run->calculated_at ?? $run->updated_at ?? now();
                    $runChanged = true;
                    $stats['run_finalized_at_filled']++;
                }

                if ($runChanged) {
                    $run->save();
                    $stats['runs_updated']++;
                }

                $groupedByUser = $run->lines->groupBy('user_id');
                foreach ($groupedByUser as $userId => $userLines) {
                    $this->cleanupUserLines($run, (int) $userId, $userLines, $stats);
                }
            }
        };

        if ($dryRun) {
            DB::beginTransaction();
            try {
                $runner();
                DB::rollBack();
            } catch (\Throwable $e) {
                DB::rollBack();
                throw $e;
            }
        } else {
            DB::transaction($runner);
        }

        $this->newLine();
        $this->info('Ringkasan cleanup:');
        foreach ($stats as $key => $value) {
            $this->line('- '.$key.': '.$value);
        }

        $this->newLine();
        $this->info($dryRun ? 'Dry run selesai.' : 'Cleanup selesai.');

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, HcmPayrollLine>  $userLines
     * @param  array<string, int>  $stats
     */
    private function cleanupUserLines(HcmPayrollRun $run, int $userId, Collection $userLines, array &$stats): void
    {
        $ordered = $userLines->sortBy([
            ['sort_order', 'asc'],
            ['id', 'asc'],
        ])->values();

        $seenSignature = [];
        $seq = 0;

        foreach ($ordered as $line) {
            $stats['lines_scanned']++;

            $signature = implode('|', [
                (string) $line->hcm_payroll_run_id,
                (string) $line->user_id,
                (string) ($line->component_code ?? ''),
                (string) ($line->kind ?? ''),
                (string) ($line->category ?? ''),
                number_format((float) $line->amount, 2, '.', ''),
                (string) $line->sort_order,
            ]);

            if (isset($seenSignature[$signature])) {
                $line->delete();
                $stats['lines_deleted_duplicates']++;

                continue;
            }
            $seenSignature[$signature] = true;

            $lineChanged = false;

            $normalizedKind = strtolower(trim((string) $line->kind));
            if (! in_array($normalizedKind, ['addition', 'deduction'], true)) {
                $normalizedKind = ((float) $line->amount) < 0 ? 'deduction' : 'addition';
            }
            if ($line->kind !== $normalizedKind) {
                $line->kind = $normalizedKind;
                $lineChanged = true;
                $stats['line_kind_normalized']++;
            }

            $componentCode = trim((string) ($line->component_code ?? ''));
            if ($line->component_code !== $componentCode) {
                $line->component_code = $componentCode;
                $lineChanged = true;
            }

            $componentName = trim((string) ($line->component_name ?? ''));
            if ($componentName === '') {
                $componentName = $componentCode !== '' ? strtoupper(str_replace('_', ' ', $componentCode)) : 'Komponen payroll';
            }
            if ($line->component_name !== $componentName) {
                $line->component_name = $componentName;
                $lineChanged = true;
            }

            $category = trim((string) ($line->category ?? ''));
            if ($line->category !== $category) {
                $line->category = $category;
                $lineChanged = true;
            }

            if ((int) $line->sort_order !== $seq) {
                $line->sort_order = $seq;
                $lineChanged = true;
                $stats['line_sort_resequenced']++;
            }
            $seq++;

            $meta = is_array($line->meta) ? $line->meta : [];
            $metaChanged = false;

            $userName = trim((string) ($line->user?->name ?? ''));
            if ($userName !== '' && (($meta['userName'] ?? null) !== $userName)) {
                $meta['userName'] = $userName;
                $metaChanged = true;
            }

            $metaPeriodYear = (int) ($run->period?->period_year ?? 0);
            $metaPeriodMonth = (int) ($run->period?->period_month ?? 0);
            if (($meta['periodYear'] ?? null) !== $metaPeriodYear) {
                $meta['periodYear'] = $metaPeriodYear;
                $metaChanged = true;
            }
            if (($meta['periodMonth'] ?? null) !== $metaPeriodMonth) {
                $meta['periodMonth'] = $metaPeriodMonth;
                $metaChanged = true;
            }
            if (($meta['runId'] ?? null) !== (int) $run->id) {
                $meta['runId'] = (int) $run->id;
                $metaChanged = true;
            }
            if (($meta['userId'] ?? null) !== $userId) {
                $meta['userId'] = $userId;
                $metaChanged = true;
            }
            if (($meta['runStatus'] ?? null) !== (string) $run->status) {
                $meta['runStatus'] = (string) $run->status;
                $metaChanged = true;
            }
            if (($meta['purpose'] ?? null) !== (string) ($run->purpose ?? HcmPayrollRun::PURPOSE_MONTHLY)) {
                $meta['purpose'] = (string) ($run->purpose ?? HcmPayrollRun::PURPOSE_MONTHLY);
                $metaChanged = true;
            }

            $existingPaymentStatus = strtolower(trim((string) ($meta['paymentStatus'] ?? '')));
            $validPayment = in_array($existingPaymentStatus, ['paid', 'partial', 'unpaid'], true);

            if ($run->status === HcmPayrollRun::STATUS_FINALIZED) {
                if (! $validPayment) {
                    $meta['paymentStatus'] = 'paid';
                    $metaChanged = true;
                    $stats['line_paymentstatus_filled_paid']++;
                } elseif ($existingPaymentStatus !== (string) $meta['paymentStatus']) {
                    $meta['paymentStatus'] = $existingPaymentStatus;
                    $metaChanged = true;
                    $stats['line_paymentstatus_normalized']++;
                }
            } else {
                if (! $validPayment) {
                    $meta['paymentStatus'] = 'unpaid';
                    $metaChanged = true;
                    $stats['line_paymentstatus_normalized']++;
                } elseif ($existingPaymentStatus !== (string) $meta['paymentStatus']) {
                    $meta['paymentStatus'] = $existingPaymentStatus;
                    $metaChanged = true;
                    $stats['line_paymentstatus_normalized']++;
                }
            }

            if ($metaChanged) {
                ksort($meta);
                $line->meta = $meta;
                $lineChanged = true;
                $stats['line_meta_standardized']++;
            }

            if ($lineChanged) {
                $line->save();
                $stats['lines_updated']++;
            }
        }
    }
}
