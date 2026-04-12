<?php

namespace App\Console\Commands;

use App\Models\HcmPayrollRun;
use App\Models\HcmThrBatch;
use App\Models\HcmThrBatchLine;
use App\Models\HcmThrDisbursement;
use App\Models\HcmThrYearlySetting;
use App\Support\Hcm\ThrSlipPublicNoAllocator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Reset QA THR:
 * - Default: hanya bersihkan jejak pembayaran & posting (gateway, slip, run payroll thr), batch + baris daftar + pengaturan tahunan tetap.
 * - --full: hapus batch, baris, disbursement, run thr, opsional pengaturan tahunan (perilaku “reset total”).
 * - --fresh-slip-numbers: (hanya tanpa --full) alokasi ulang `thr_slip_public_no` per baris (ULID baru); bermanfaat QA slip/PDF.
 *
 * SQL manual (MySQL, setara default tanpa --full) — lihat juga `docs/features/payroll-runs/THR_RESET_MANUAL.sql`:
 *
 * - `thr_slip_public_no` **jangan di-NULL** (NOT NULL + identitas dokumen). Biarkan agar nomor slip sama saat regenerate PDF.
 * - Untuk nomor slip baru tanpa hapus batch: pakai `php artisan hcm:reset-thr-test-data --fresh-slip-numbers`.
 */
class HcmResetThrTestDataCommand extends Command
{
    protected $signature = 'hcm:reset-thr-test-data {--year= : Batasi ke satu tahun kalender} {--full : Hapus batch & baris THR (+ pengaturan jika tidak --keep-settings)} {--keep-settings : Hanya untuk --full: pertahankan hcm_thr_yearly_settings} {--fresh-slip-numbers : Tanpa --full: generate ulang thr_slip_public_no per baris (QA)}';

    protected $description = 'Default: reset status bayar/posting THR saja. Opsi --full: hapus seluruh batch THR (data lama). Opsi --fresh-slip-numbers: nomor slip baru per baris.';

    public function handle(): int
    {
        $year = $this->option('year');
        $full = (bool) $this->option('full');
        $keepSettings = (bool) $this->option('keep-settings');

        $yearFilter = $year !== null && $year !== '' ? (int) $year : null;
        if ($yearFilter !== null && ($yearFilter < 2000 || $yearFilter > 2100)) {
            $this->error('Opsi --year harus antara 2000 dan 2100.');

            return self::FAILURE;
        }

        $freshSlipNumbers = (bool) $this->option('fresh-slip-numbers');
        if ($freshSlipNumbers && $full) {
            $this->warn('Opsi --fresh-slip-numbers diabaikan pada mode --full (baris dihapus; nomor slip baru saat generate batch lagi).');
            $freshSlipNumbers = false;
        }

        $warn = $full
            ? 'Mode FULL: batch THR dan barisnya akan dihapus'.($keepSettings ? '; pengaturan tahunan dipertahankan (--keep-settings).' : '; pengaturan tahunan ikut dihapus.')
            : 'Hanya reset pembayaran & posting: run payroll thr, disbursement, kolom bayar/slip di baris; batch & daftar karyawan tetap.'
                .($freshSlipNumbers ? ' Nomor slip (`thr_slip_public_no`) akan diganti ULID baru per baris.' : '');

        if (! $this->confirm($warn.' Lanjut?', true)) {
            $this->info('Dibatalkan.');

            return self::SUCCESS;
        }

        if ($full) {
            return $this->runFullReset($yearFilter, $keepSettings);
        }

        return $this->runPaymentOnlyReset($yearFilter, $freshSlipNumbers);
    }

    private function runPaymentOnlyReset(?int $yearFilter, bool $freshSlipNumbers): int
    {
        $runsDeleted = 0;
        $disbursementsDeleted = 0;
        $linesReset = 0;
        $batchesTouched = 0;
        $yearsToCleanSlips = [];

        DB::transaction(function () use ($yearFilter, $freshSlipNumbers, &$runsDeleted, &$disbursementsDeleted, &$linesReset, &$batchesTouched, &$yearsToCleanSlips) {
            $q = HcmThrBatch::query();
            if ($yearFilter !== null) {
                $q->where('calendar_year', $yearFilter);
            }

            foreach ($q->cursor() as $batch) {
                /** @var HcmThrBatch $batch */
                $batchesTouched++;

                $disbursementsDeleted += HcmThrDisbursement::query()
                    ->where('hcm_thr_batch_id', $batch->id)
                    ->delete();

                $linesReset += HcmThrBatchLine::query()
                    ->where('hcm_thr_batch_id', $batch->id)
                    ->update([
                        'payment_status' => HcmThrBatchLine::PAYMENT_UNPAID,
                        'payment_failure_reason' => null,
                        'payment_gateway_ref' => null,
                        'paid_at' => null,
                        'slip_storage_path' => null,
                        'slip_generated_at' => null,
                        'slip_notify_sent_at' => null,
                        'last_disbursement_id' => null,
                    ]);

                if ($freshSlipNumbers) {
                    $cy = (int) $batch->calendar_year;
                    foreach (
                        HcmThrBatchLine::query()
                            ->where('hcm_thr_batch_id', $batch->id)
                            ->orderBy('id')
                            ->cursor() as $line
                    ) {
                        $line->update([
                            'thr_slip_public_no' => ThrSlipPublicNoAllocator::allocate($cy),
                        ]);
                    }
                }

                $skipBatchMeta = false;
                if ($batch->hcm_payroll_run_id !== null) {
                    $run = HcmPayrollRun::query()->find($batch->hcm_payroll_run_id);
                    if ($run !== null && $run->purpose === HcmPayrollRun::PURPOSE_THR) {
                        $run->delete();
                        $runsDeleted++;
                    } elseif ($run !== null) {
                        $this->warn("Batch {$batch->id} terhubung ke run non-THR (id {$run->id}) — run & status batch tidak diubah; kolom bayar/slip pada baris sudah di-reset.");
                        $skipBatchMeta = true;
                    }
                }

                if (! $skipBatchMeta) {
                    $batch->update([
                        'status' => HcmThrBatch::STATUS_DRAFT,
                        'assigned_at' => null,
                        'assigned_by_user_id' => null,
                        'hcm_payroll_period_id' => null,
                        'hcm_payroll_run_id' => null,
                    ]);
                }

                $yearsToCleanSlips[(int) $batch->calendar_year] = true;
            }
        });

        $slipRoot = storage_path('app/private/thr-slips');
        foreach (array_keys($yearsToCleanSlips) as $calYear) {
            $yearDir = $slipRoot.DIRECTORY_SEPARATOR.$calYear;
            if (is_dir($yearDir)) {
                File::deleteDirectory($yearDir);
                $this->line("Slip PDF tahun {$calYear} dikosongkan.");
            }
        }

        $this->info(sprintf(
            'Reset pembayaran selesai. Batch disentuh: %d | Run THR terhapus: %d | Disbursement terhapus: %d | Baris di-reset kolom bayar/slip: %d.%s',
            $batchesTouched,
            $runsDeleted,
            $disbursementsDeleted,
            $linesReset,
            $freshSlipNumbers ? ' thr_slip_public_no di-generate ulang per baris.' : ''
        ));

        return self::SUCCESS;
    }

    private function runFullReset(?int $yearFilter, bool $keepSettings): int
    {
        $runsDeleted = 0;
        $batchesDeleted = 0;
        $settingsDeleted = 0;

        DB::transaction(function () use ($yearFilter, $keepSettings, &$runsDeleted, &$batchesDeleted, &$settingsDeleted) {
            if ($yearFilter !== null) {
                $runIds = HcmThrBatch::query()
                    ->where('calendar_year', $yearFilter)
                    ->whereNotNull('hcm_payroll_run_id')
                    ->pluck('hcm_payroll_run_id')
                    ->unique()
                    ->filter()
                    ->values()
                    ->all();

                if ($runIds !== []) {
                    $runsDeleted = HcmPayrollRun::query()
                        ->where('purpose', HcmPayrollRun::PURPOSE_THR)
                        ->whereIn('id', $runIds)
                        ->delete();
                }

                $batchesDeleted = HcmThrBatch::query()->where('calendar_year', $yearFilter)->delete();

                if (! $keepSettings) {
                    $settingsDeleted = HcmThrYearlySetting::query()->where('calendar_year', $yearFilter)->delete();
                }
            } else {
                $runsDeleted = HcmPayrollRun::query()
                    ->where('purpose', HcmPayrollRun::PURPOSE_THR)
                    ->delete();

                $batchesDeleted = HcmThrBatch::query()->delete();

                if (! $keepSettings) {
                    $settingsDeleted = HcmThrYearlySetting::query()->delete();
                }
            }
        });

        $slipRoot = storage_path('app/private/thr-slips');
        if ($yearFilter !== null) {
            $yearDir = $slipRoot.DIRECTORY_SEPARATOR.$yearFilter;
            if (is_dir($yearDir)) {
                File::deleteDirectory($yearDir);
                $this->line("Folder slip PDF tahun {$yearFilter} dikosongkan.");
            }
        } elseif (is_dir($slipRoot)) {
            File::deleteDirectory($slipRoot);
            $this->line('Folder slip PDF dikosongkan: thr-slips');
        }

        $keepLabel = $keepSettings ? 'ya' : 'tidak';
        $this->info(sprintf(
            'Mode full selesai. Run THR terhapus: %d | Batch terhapus: %d | Pengaturan tahunan terhapus: %d (keep-settings=%s).',
            $runsDeleted,
            $batchesDeleted,
            $settingsDeleted,
            $keepLabel
        ));

        return self::SUCCESS;
    }
}
