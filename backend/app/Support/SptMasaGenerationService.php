<?php

namespace App\Support;

use App\Models\CompanyUser;
use App\Models\EmployeeProfile;
use App\Models\EmployeeTaxProfile;
use App\Models\HcmPayrollLine;
use App\Models\HcmPayrollPeriod;
use App\Models\HcmPayrollRun;
use App\Models\HcmSptMasaDetail;
use App\Models\HcmSptMasaHeader;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SptMasaGenerationService
{
    /**
     * Parse a "YYYY-MM" periode string into [year, month].
     */
    public static function parsePeriode(string $periode): array
    {
        $parts = explode('-', $periode);
        return [(int) ($parts[0] ?? 0), (int) ($parts[1] ?? 0)];
    }

    /**
     * Find the payroll period model for a given YYYY-MM string.
     */
    public static function findPeriod(int $companyId, string $periode): ?HcmPayrollPeriod
    {
        [$year, $month] = self::parsePeriode($periode);
        if ($year < 2000 || $month < 1 || $month > 12) {
            return null;
        }

        return HcmPayrollPeriod::query()
            ->where('company_id', $companyId)
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->first();
    }

    /**
     * Check if at least one finalized monthly run exists for this period.
     */
    public static function hasFinalizedMonthlyRun(int $companyId, string $periode): bool
    {
        $period = self::findPeriod($companyId, $periode);
        if (! $period) {
            return false;
        }

        return HcmPayrollRun::query()
            ->where('company_id', $companyId)
            ->where('hcm_payroll_period_id', $period->id)
            ->where('status', HcmPayrollRun::STATUS_FINALIZED)
            ->where('purpose', HcmPayrollRun::PURPOSE_MONTHLY)
            ->exists();
    }

    /**
     * Generate (or regenerate) a SPT Masa Header and its Detail rows.
     * Uses DB transaction for atomicity.
     *
     * Returns the header model.
     */
    public static function generate(
        int $companyId,
        string $periode,
        int $actorUserId,
        ?string $generationKey = null,
        ?HcmSptMasaHeader $existingHeader = null
    ): HcmSptMasaHeader {
        return DB::transaction(function () use ($companyId, $periode, $actorUserId, $generationKey, $existingHeader) {
            $period = self::findPeriod($companyId, $periode);

            // Collect all finalized monthly runs for the period.
            $runIds = HcmPayrollRun::query()
                ->where('company_id', $companyId)
                ->where('hcm_payroll_period_id', $period->id)
                ->where('status', HcmPayrollRun::STATUS_FINALIZED)
                ->where('purpose', HcmPayrollRun::PURPOSE_MONTHLY)
                ->pluck('id')
                ->toArray();

            // Collect owner user IDs to exclude — owners are not employees for tax purposes.
            $ownerUserIds = CompanyUser::query()
                ->where('company_id', $companyId)
                ->where('role', 'owner')
                ->pluck('user_id')
                ->toArray();

            // Aggregate lines per user, excluding owners.
            $aggregates = HcmPayrollLine::query()
                ->whereIn('hcm_payroll_run_id', $runIds)
                ->where('company_id', $companyId)
                ->when(! empty($ownerUserIds), fn ($q) => $q->whereNotIn('user_id', $ownerUserIds))
                ->select([
                    'user_id',
                    DB::raw("SUM(CASE WHEN kind = 'addition' AND (category LIKE 'pph21_taxable_%' OR category = 'addition') THEN amount ELSE 0 END) as bruto"),
                    DB::raw("SUM(CASE WHEN kind = 'deduction' AND category LIKE 'pph21%' THEN amount ELSE 0 END) as pph21"),
                ])
                ->groupBy('user_id')
                ->get();

            if ($existingHeader) {
                // Regenerate: update in-place, wipe old details.
                $existingHeader->details()->delete();
                $header = $existingHeader;
                $header->generated_at = now();
                $header->version += 1;
            } else {
                $header = new HcmSptMasaHeader([
                    'company_id' => $companyId,
                    'periode' => $periode,
                    'status' => HcmSptMasaHeader::STATUS_DRAFT,
                    'generation_key' => $generationKey,
                    'generated_at' => now(),
                    'created_by_user_id' => $actorUserId,
                    'version' => 1,
                ]);
            }

            $totalBruto = 0.0;
            $totalPph21 = 0.0;
            $totalKaryawan = 0;
            $detailsToInsert = [];

            foreach ($aggregates as $agg) {
                $userId = (int) $agg->user_id;
                if ($userId <= 0) {
                    continue;
                }

                $user = User::query()->find($userId);
                if (! $user) {
                    continue;
                }

                $taxProfile = EmployeeTaxProfile::query()
                    ->where('employee_id', $userId)
                    ->latest('effective_date')
                    ->first();

                $employeeProfile = EmployeeProfile::query()
                    ->where('user_id', $userId)
                    ->first();

                $contractType = $employeeProfile?->contract_type ?? null;
                $employmentType = self::mapContractType($contractType);
                if ($employmentType === null) {
                    // Skip employees with unrecognized contract type.
                    continue;
                }

                $kategoriSpt = self::mapKategoriSpt($employmentType);
                $bruto = round((float) $agg->bruto, 2);
                $pph21 = round((float) $agg->pph21, 2);

                if ($bruto <= 0 && $pph21 <= 0) {
                    continue;
                }

                $totalBruto += $bruto;
                $totalPph21 += $pph21;
                $totalKaryawan++;

                $detailsToInsert[] = [
                    'uuid' => (string) Str::uuid(),
                    'hcm_spt_masa_header_id' => 0, // placeholder, filled after header save
                    'hcm_spt_masa_header_uuid' => null,
                    'company_id' => $companyId,
                    'user_id' => $userId,
                    'user_uuid' => (string) ($user->uuid ?? ''),
                    'nama' => (string) $user->name,
                    'npwp' => $taxProfile ? self::normalizeNpwp((string) ($taxProfile->npwp ?? '')) : null,
                    'nik' => $employeeProfile ? (string) ($employeeProfile->nik ?? '') : null,
                    'employment_type' => $employmentType,
                    'kategori_spt' => $kategoriSpt,
                    'bruto' => $bruto,
                    'pph21' => $pph21,
                    'bukti_potong_type' => $employmentType === HcmSptMasaDetail::EMPLOYMENT_PERMANENT ? 'A1' : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            $header->total_bruto = round($totalBruto, 2);
            $header->total_pph21 = round($totalPph21, 2);
            $header->total_karyawan = $totalKaryawan;
            $header->save();

            // Fill header references in details.
            foreach ($detailsToInsert as &$detail) {
                $detail['hcm_spt_masa_header_id'] = $header->id;
                $detail['hcm_spt_masa_header_uuid'] = (string) ($header->uuid ?? '');
            }
            unset($detail);

            if (! empty($detailsToInsert)) {
                HcmSptMasaDetail::query()->insert($detailsToInsert);
            }

            return $header->fresh(['details']);
        });
    }

    /**
     * Map contract_type from EmployeeProfile to SPT employment_type.
     * Returns null for unrecognized types (skip in MVP).
     */
    public static function mapContractType(?string $contractType): ?string
    {
        return match (strtolower((string) $contractType)) {
            'permanent', 'pkwtt' => HcmSptMasaDetail::EMPLOYMENT_PERMANENT,
            'contract', 'pkwt' => HcmSptMasaDetail::EMPLOYMENT_CONTRACT,
            default => null,
        };
    }

    /**
     * Map employment_type to kategori_spt.
     */
    public static function mapKategoriSpt(string $employmentType): string
    {
        return match ($employmentType) {
            HcmSptMasaDetail::EMPLOYMENT_PERMANENT => HcmSptMasaDetail::KATEGORI_PEGAWAI_TETAP,
            HcmSptMasaDetail::EMPLOYMENT_CONTRACT,
            HcmSptMasaDetail::EMPLOYMENT_INTERN => HcmSptMasaDetail::KATEGORI_TIDAK_TETAP,
            HcmSptMasaDetail::EMPLOYMENT_NON_EMPLOYEE => HcmSptMasaDetail::KATEGORI_NON_PEGAWAI,
            default => HcmSptMasaDetail::KATEGORI_TIDAK_TETAP,
        };
    }

    /**
     * Normalize NPWP: strip `.` and `-` separators.
     */
    public static function normalizeNpwp(string $npwp): string
    {
        return preg_replace('/[.\-]/', '', $npwp) ?? '';
    }
}
