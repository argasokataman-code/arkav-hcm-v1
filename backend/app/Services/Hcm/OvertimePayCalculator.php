<?php

namespace App\Services\Hcm;

class OvertimePayCalculator
{
    /**
     * @return array<string, mixed>
     */
    public function calculate(
        float $baseMonthlySalary,
        float $fixedAllowance,
        int $minutes,
        string $dayType,
        int $weeklyWorkDays = 5
    ): array {
        $monthlyWage = max(0.0, $baseMonthlySalary + $fixedAllowance);
        $hourly = $monthlyWage / 173;
        $hours = max(0.0, $minutes / 60);

        $segments = [];
        $totalMultiplierHours = 0.0;

        if ($dayType === 'workday') {
            $firstHour = min(1.0, $hours);
            if ($firstHour > 0) {
                $segments[] = [
                    'label' => 'Jam pertama',
                    'hours' => round($firstHour, 2),
                    'multiplier' => 1.5,
                ];
                $totalMultiplierHours += $firstHour * 1.5;
            }
            $nextHours = max(0.0, $hours - 1.0);
            if ($nextHours > 0) {
                $segments[] = [
                    'label' => 'Jam berikutnya',
                    'hours' => round($nextHours, 2),
                    'multiplier' => 2.0,
                ];
                $totalMultiplierHours += $nextHours * 2.0;
            }
        } else {
            // PP 35/2021 simplified matrix.
            if ($weeklyWorkDays <= 5) {
                // 8h @2x, hour-9 @3x, hour-10+ @4x
                $h1 = min(8.0, $hours);
                $h2 = min(1.0, max(0.0, $hours - 8.0));
                $h3 = max(0.0, $hours - 9.0);
                if ($h1 > 0) {
                    $segments[] = ['label' => '8 jam pertama', 'hours' => round($h1, 2), 'multiplier' => 2.0];
                    $totalMultiplierHours += $h1 * 2.0;
                }
                if ($h2 > 0) {
                    $segments[] = ['label' => 'Jam ke-9', 'hours' => round($h2, 2), 'multiplier' => 3.0];
                    $totalMultiplierHours += $h2 * 3.0;
                }
                if ($h3 > 0) {
                    $segments[] = ['label' => 'Jam ke-10 dst', 'hours' => round($h3, 2), 'multiplier' => 4.0];
                    $totalMultiplierHours += $h3 * 4.0;
                }
            } else {
                // 6-day week approximation: 7h @2x, 8th @3x, 9th+ @4x
                $h1 = min(7.0, $hours);
                $h2 = min(1.0, max(0.0, $hours - 7.0));
                $h3 = max(0.0, $hours - 8.0);
                if ($h1 > 0) {
                    $segments[] = ['label' => '7 jam pertama', 'hours' => round($h1, 2), 'multiplier' => 2.0];
                    $totalMultiplierHours += $h1 * 2.0;
                }
                if ($h2 > 0) {
                    $segments[] = ['label' => 'Jam ke-8', 'hours' => round($h2, 2), 'multiplier' => 3.0];
                    $totalMultiplierHours += $h2 * 3.0;
                }
                if ($h3 > 0) {
                    $segments[] = ['label' => 'Jam ke-9 dst', 'hours' => round($h3, 2), 'multiplier' => 4.0];
                    $totalMultiplierHours += $h3 * 4.0;
                }
            }
        }

        $totalPay = $hourly * $totalMultiplierHours;

        return [
            'monthlyWage' => round($monthlyWage, 2),
            'hourlyWage' => round($hourly, 2),
            'hours' => round($hours, 2),
            'dayType' => $dayType,
            'weeklyWorkDays' => $weeklyWorkDays,
            'segments' => $segments,
            'totalOvertimePay' => round($totalPay, 2),
            'regulationNote' => 'Perhitungan acuan PP No. 35 Tahun 2021 (ringkas).',
        ];
    }
}
