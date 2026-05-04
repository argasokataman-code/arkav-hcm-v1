<?php

namespace App\Services\Hcm;

/**
 * BpjsContributionCalculator
 *
 * Kalkulasi iuran BPJS dengan logika:
 * - JKK: rate berbasis risk category (1-5) untuk porsi perusahaan
 * - JP:  salary cap (gaji di-cap sebelum dikalikan tarif)
 * - BPJS Kesehatan: salary cap (gaji di-cap sebelum dikalikan tarif)
 *
 * Output per kalkulasi:
 * [
 *   'program'            => string,
 *   'contribution_party' => string,
 *   'base_salary_used'   => float,
 *   'rate_used'          => float,    // dalam persen, mis 1.0 = 1%
 *   'contribution_amount'=> float,
 *   'rate_source'        => string,   // 'risk_category' | 'policy' | 'default'
 *   'cap_applied'        => bool,
 * ]
 */
final class BpjsContributionCalculator
{
    /**
     * Mapping risk category JKK → tarif (persen) sesuai PP No. 44/2015
     *
     * @var array<int, float>
     */
    public const JKK_RISK_RATES = [
        1 => 0.24,
        2 => 0.54,
        3 => 0.89,
        4 => 1.27,
        5 => 1.74,
    ];

    /**
     * Default salary cap BPJS Kesehatan (Perpres 75/2019)
     * 2x PTKP K/1 = 12.000.000
     */
    public const BPJS_KES_DEFAULT_CAP = 12_000_000.0;

    /**
     * Default salary cap JP (PP 45/2015) — diupdate berkala tiap tahun
     * Nilai 2026
     */
    public const JP_DEFAULT_CAP = 9_077_600.0;

    /**
     * Hitung iuran BPJS Kesehatan (pekerja atau perusahaan).
     *
     * @param  float       $grossSalary   Gaji bruto (pokok + tunjangan tetap)
     * @param  float       $ratePercent   Tarif dari policy (mis. 1.0 untuk 1%)
     * @param  string      $party         'employee' | 'employer'
     * @param  float|null  $salaryCap     Override cap tenant; null → pakai default
     * @return array<string, mixed>
     */
    public function calculateBpjsKesehatan(
        float $grossSalary,
        float $ratePercent,
        string $party = 'employee',
        ?float $salaryCap = null
    ): array {
        $cap = $salaryCap ?? self::BPJS_KES_DEFAULT_CAP;
        $capApplied = $grossSalary > $cap;
        $baseUsed = $capApplied ? $cap : $grossSalary;

        return $this->buildResult('bpjs_kesehatan', $party, $grossSalary, $baseUsed, $ratePercent, 'policy', $capApplied);
    }

    /**
     * Hitung iuran JHT (pekerja atau perusahaan) — tidak ada cap, rate flat.
     *
     * @param  float   $grossSalary
     * @param  float   $ratePercent
     * @param  string  $party
     * @return array<string, mixed>
     */
    public function calculateJht(float $grossSalary, float $ratePercent, string $party = 'employee'): array
    {
        return $this->buildResult('jht', $party, $grossSalary, $grossSalary, $ratePercent, 'policy', false);
    }

    /**
     * Hitung iuran JP (pekerja atau perusahaan) dengan salary cap.
     *
     * @param  float       $grossSalary
     * @param  float       $ratePercent
     * @param  string      $party
     * @param  float|null  $salaryCap   Override cap tenant; null → pakai default
     * @return array<string, mixed>
     */
    public function calculateJp(
        float $grossSalary,
        float $ratePercent,
        string $party = 'employee',
        ?float $salaryCap = null
    ): array {
        $cap = $salaryCap ?? self::JP_DEFAULT_CAP;
        $capApplied = $grossSalary > $cap;
        $baseUsed = $capApplied ? $cap : $grossSalary;

        return $this->buildResult('jp', $party, $grossSalary, $baseUsed, $ratePercent, 'policy', $capApplied);
    }

    /**
     * Hitung iuran JKK — hanya untuk porsi perusahaan (employer).
     * Rate ditentukan dari risk_category, bukan tarif bebas.
     *
     * @param  float    $grossSalary
     * @param  int      $riskCategory  1–5
     * @param  float|null $overrideRate  Jika diisi (tenant set), pakai ini (harus dalam range risk category)
     * @return array<string, mixed>
     *
     * @throws \InvalidArgumentException jika risk_category di luar 1–5
     */
    public function calculateJkk(float $grossSalary, int $riskCategory, ?float $overrideRate = null): array
    {
        if ($riskCategory < 1 || $riskCategory > 5) {
            throw new \InvalidArgumentException("JKK risk_category harus antara 1 dan 5, dapat: {$riskCategory}");
        }

        $regulatoryRate = self::JKK_RISK_RATES[$riskCategory];

        // Jika tenant mengisi override rate, validasi masih dalam batas regulasi
        $rateUsed = $regulatoryRate;
        $rateSource = 'risk_category';

        if ($overrideRate !== null) {
            // Batas: tidak boleh melebihi rate risk tertinggi (1.74) dan tidak boleh kurang dari 0
            $maxAllowed = self::JKK_RISK_RATES[5];
            $rateUsed = max(0.0, min($overrideRate, $maxAllowed));
            $rateSource = 'tenant_override';
        }

        return $this->buildResult('jkk', 'employer', $grossSalary, $grossSalary, $rateUsed, $rateSource, false, [
            'riskCategory' => $riskCategory,
            'regulatoryRate' => $regulatoryRate,
        ]);
    }

    /**
     * Hitung iuran JKM — hanya untuk porsi perusahaan, rate flat dari policy.
     *
     * @param  float   $grossSalary
     * @param  float   $ratePercent
     * @return array<string, mixed>
     */
    public function calculateJkm(float $grossSalary, float $ratePercent): array
    {
        return $this->buildResult('jkm', 'employer', $grossSalary, $grossSalary, $ratePercent, 'policy', false);
    }

    /**
     * Entry point generik — dispatch ke metode spesifik berdasarkan programCode.
     *
     * @param  string      $programCode       'bpjs_kesehatan' | 'jht' | 'jp' | 'jkk' | 'jkm'
     * @param  string      $party             'employee' | 'employer'
     * @param  float       $grossSalary
     * @param  float       $ratePercent       Tarif dari policy/baseline
     * @param  array<string, mixed>  $options {
     *     riskCategory?: int,        // Untuk JKK
     *     jkkOverrideRate?: float,   // Optional override JKK dalam batas regulasi
     *     jpSalaryCap?: float,       // Override cap JP
     *     bpjsKesSalaryCap?: float,  // Override cap BPJS Kesehatan
     * }
     * @return array<string, mixed>
     */
    public function calculate(
        string $programCode,
        string $party,
        float $grossSalary,
        float $ratePercent,
        array $options = []
    ): array {
        return match ($programCode) {
            'bpjs_kesehatan' => $this->calculateBpjsKesehatan(
                $grossSalary,
                $ratePercent,
                $party,
                $options['bpjsKesSalaryCap'] ?? null
            ),
            'jht' => $this->calculateJht($grossSalary, $ratePercent, $party),
            'jp'  => $this->calculateJp(
                $grossSalary,
                $ratePercent,
                $party,
                $options['jpSalaryCap'] ?? null
            ),
            'jkk' => $this->calculateJkk(
                $grossSalary,
                (int) ($options['riskCategory'] ?? 1),
                $options['jkkOverrideRate'] ?? null
            ),
            'jkm' => $this->calculateJkm($grossSalary, $ratePercent),
            default => throw new \InvalidArgumentException("Program BPJS tidak dikenal: {$programCode}"),
        };
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function buildResult(
        string $program,
        string $party,
        float $grossSalary,
        float $baseUsed,
        float $ratePercent,
        string $rateSource,
        bool $capApplied,
        array $extra = []
    ): array {
        $amount = round($baseUsed * ($ratePercent / 100), 2);

        return array_merge([
            'program'             => $program,
            'contribution_party'  => $party,
            'gross_salary'        => round($grossSalary, 2),
            'base_salary_used'    => round($baseUsed, 2),
            'rate_used'           => $ratePercent,
            'contribution_amount' => $amount,
            'rate_source'         => $rateSource,
            'cap_applied'         => $capApplied,
        ], $extra);
    }
}
