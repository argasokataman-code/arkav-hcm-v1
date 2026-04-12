<?php

namespace App\Services\Hcm;

use Carbon\Carbon;

/**
 * Estimasi THR bruto berdasarkan Permenaker No. 6 Tahun 2016 (pro rata masa kerja 1–11 bulan).
 *
 * Upah acuan: gaji pokok + tunjangan tetap (tunjangan tidak tetap tidak masuk).
 * Masa kerja (M): bulan penuh antara tanggal bergabung dan tanggal cut-off (H-1 Lebaran / acuan perusahaan),
 * dengan aturan hari: jika tanggal cut-off belum mencapai tanggal yang sama dengan tanggal bergabung,
 * bulan berjalan tidak dihitung penuh (floor).
 */
final class ThrProRataCalculator
{
    public const REGULATION_LABEL = 'Permenaker No. 6 Tahun 2016 (ringkas — verifikasi kebijakan internal & pajak)';

    /**
     * @return array{
     *     eligible: bool,
     *     status: string,
     *     monthsOfService: int,
     *     multiplier: float,
     *     referenceMonthlyWage: float,
     *     thrGross: float,
     *     joinDate: string,
     *     cutoffDate: string,
     *     notes: list<string>
     * }
     */
    public function calculate(
        string|\DateTimeInterface $joinDate,
        string|\DateTimeInterface $cutoffDate,
        float $baseMonthlySalary,
        float $fixedMonthlyAllowance = 0.0,
    ): array {
        $join = Carbon::parse($joinDate)->startOfDay();
        $cutoff = Carbon::parse($cutoffDate)->startOfDay();
        $notes = [
            'Upah acuan = gaji pokok + tunjangan tetap (sesuai permen; tunjangan tidak tetap tidak diikutkan).',
            'THR sering dibayar terpisah dari jadwal gaji bulanan (dua pembayaran di bulan THR bila perusahaan memisahkan transfer).',
            'PPh 21 (TER / aturan terkini) dihitung terpisah pada slip — tidak termasuk di sini.',
        ];

        if ($cutoff->lt($join)) {
            return [
                'eligible' => false,
                'status' => 'invalid_dates',
                'monthsOfService' => 0,
                'multiplier' => 0.0,
                'referenceMonthlyWage' => round($baseMonthlySalary + $fixedMonthlyAllowance, 2),
                'thrGross' => 0.0,
                'joinDate' => $join->toDateString(),
                'cutoffDate' => $cutoff->toDateString(),
                'notes' => array_merge($notes, ['Cut-off harus sama atau setelah tanggal bergabung.']),
            ];
        }

        $m = $this->wholeMonthsBetween($join, $cutoff);
        $reference = round(max(0, $baseMonthlySalary) + max(0, $fixedMonthlyAllowance), 2);

        if ($m < 1) {
            return [
                'eligible' => false,
                'status' => 'not_eligible',
                'monthsOfService' => $m,
                'multiplier' => 0.0,
                'referenceMonthlyWage' => $reference,
                'thrGross' => 0.0,
                'joinDate' => $join->toDateString(),
                'cutoffDate' => $cutoff->toDateString(),
                'notes' => array_merge($notes, ['Masa kerja kurang dari 1 bulan penuh → THR = 0.']),
            ];
        }

        if ($m >= 12) {
            $thr = round($reference, 2);

            return [
                'eligible' => true,
                'status' => 'full',
                'monthsOfService' => $m,
                'multiplier' => 1.0,
                'referenceMonthlyWage' => $reference,
                'thrGross' => $thr,
                'joinDate' => $join->toDateString(),
                'cutoffDate' => $cutoff->toDateString(),
                'notes' => array_merge($notes, ['Masa kerja ≥ 12 bulan penuh → THR = 1 × upah acuan.']),
            ];
        }

        $multiplier = $m / 12.0;
        $thr = round($reference * $multiplier, 2);

        return [
            'eligible' => true,
            'status' => 'pro_rata',
            'monthsOfService' => $m,
            'multiplier' => round($multiplier, 6),
            'referenceMonthlyWage' => $reference,
            'thrGross' => $thr,
            'joinDate' => $join->toDateString(),
            'cutoffDate' => $cutoff->toDateString(),
            'notes' => array_merge($notes, [
                sprintf('Pro rata: (%d / 12) × upah acuan.', $m),
            ]),
        ];
    }

    /**
     * Jumlah bulan penuh antara $start dan $end (inklusif aturan hari: belum genap tanggal → bulan tidak dihitung).
     */
    public function wholeMonthsBetween(Carbon $start, Carbon $end): int
    {
        if ($end->lt($start)) {
            return -1;
        }

        $months = ($end->year - $start->year) * 12 + ($end->month - $start->month);
        if ($end->day < $start->day) {
            $months--;
        }

        return max(0, $months);
    }
}
