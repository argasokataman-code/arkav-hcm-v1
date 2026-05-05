<?php

namespace App\Support;

use App\Models\HcmPayrollLine;
use App\Models\HcmPayrollRun;
use App\Models\HcmSptMasaHeader;
use Illuminate\Support\Facades\DB;

class SptMasaValidationService
{
    /**
     * Validate header totals against live payroll lines.
     * Returns array of error codes (empty = valid).
     */
    public static function validateTotals(HcmSptMasaHeader $header): array
    {
        $errors = [];
        $period = SptMasaGenerationService::findPeriod((int) $header->company_id, (string) $header->periode);
        if (! $period) {
            $errors[] = 'SPT_PAYROLL_NOT_FINAL';
            return $errors;
        }

        $runIds = HcmPayrollRun::query()
            ->where('company_id', $header->company_id)
            ->where('hcm_payroll_period_id', $period->id)
            ->where('status', HcmPayrollRun::STATUS_FINALIZED)
            ->where('purpose', HcmPayrollRun::PURPOSE_MONTHLY)
            ->pluck('id')
            ->toArray();

        if (empty($runIds)) {
            $errors[] = 'SPT_PAYROLL_NOT_FINAL';
            return $errors;
        }

        $livePph21 = (float) HcmPayrollLine::query()
            ->whereIn('hcm_payroll_run_id', $runIds)
            ->where('company_id', $header->company_id)
            ->where('kind', 'deduction')
            ->where(DB::raw("1"), '=', DB::raw("1"))
            ->where(function ($q): void {
                $q->where('category', 'LIKE', 'pph21%');
            })
            ->sum('amount');

        $liveBruto = (float) HcmPayrollLine::query()
            ->whereIn('hcm_payroll_run_id', $runIds)
            ->where('company_id', $header->company_id)
            ->where('kind', 'addition')
            ->where(function ($q): void {
                $q->where('category', 'LIKE', 'pph21_taxable_%')
                  ->orWhere('category', 'addition');
            })
            ->sum('amount');

        $tolerance = 0.02;

        if (abs((float) $header->total_pph21 - $livePph21) > $tolerance) {
            $errors[] = 'SPT_TOTAL_MISMATCH';
        }
        if (abs((float) $header->total_bruto - $liveBruto) > $tolerance) {
            $errors[] = 'SPT_TOTAL_MISMATCH';
        }

        return array_values(array_unique($errors));
    }

    /**
     * Validate all detail rows are complete for submit.
     * Returns array of error codes (empty = valid).
     */
    public static function validateDetails(HcmSptMasaHeader $header): array
    {
        $errors = [];
        $details = $header->details()->get();

        if ($details->isEmpty()) {
            $errors[] = 'SPT_DETAIL_INCOMPLETE';
            return $errors;
        }

        foreach ($details as $detail) {
            if (
                empty($detail->nama) ||
                empty($detail->kategori_spt) ||
                (float) $detail->bruto <= 0
            ) {
                $errors[] = 'SPT_DETAIL_INCOMPLETE';
                break;
            }

            if (! empty($detail->npwp) && ! self::isValidNpwp($detail->npwp)) {
                $errors[] = 'SPT_DETAIL_INCOMPLETE';
                break;
            }
        }

        return $errors;
    }

    /**
     * NPWP must be 15 or 16 digits after normalization.
     */
    public static function isValidNpwp(string $npwp): bool
    {
        $normalized = SptMasaGenerationService::normalizeNpwp($npwp);
        $len = strlen($normalized);
        return $len === 15 || $len === 16;
    }
}
