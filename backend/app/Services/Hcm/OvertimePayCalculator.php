<?php

namespace App\Services\Hcm;

class OvertimePayCalculator
{
    public const DAY_TYPE_WORKDAY = 'workday';

    public const DAY_TYPE_WEEKLY_REST = 'weekly_rest_day';

    public const DAY_TYPE_PUBLIC_HOLIDAY = 'public_holiday';

    public const DAY_TYPE_WEEKLY_REST_SHORT = 'weekly_rest_day_short';

    /**
     * @return array<string, mixed>
     */
    public function calculate(
        float $baseMonthlySalary,
        float $fixedAllowance,
        int $minutes,
        string $dayType,
        int $weeklyWorkDays = 5,
        bool $includeRaw = false
    ): array {
        $monthlyWage = max(0.0, $baseMonthlySalary + $fixedAllowance);
        $hourly = $monthlyWage / 173;
        $hours = max(0.0, $minutes / 60);
        $normalizedDayType = $this->normalizeDayType($dayType);

        $segments = [];
        $totalMultiplierHours = 0.0;

        if ($normalizedDayType === self::DAY_TYPE_WORKDAY) {
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
            if ($weeklyWorkDays <= 5) {
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
            } elseif ($normalizedDayType === self::DAY_TYPE_WEEKLY_REST_SHORT) {
                $h1 = min(5.0, $hours);
                $h2 = min(1.0, max(0.0, $hours - 5.0));
                $h3 = max(0.0, $hours - 6.0);
                if ($h1 > 0) {
                    $segments[] = ['label' => '5 jam pertama', 'hours' => round($h1, 2), 'multiplier' => 2.0];
                    $totalMultiplierHours += $h1 * 2.0;
                }
                if ($h2 > 0) {
                    $segments[] = ['label' => 'Jam ke-6', 'hours' => round($h2, 2), 'multiplier' => 3.0];
                    $totalMultiplierHours += $h2 * 3.0;
                }
                if ($h3 > 0) {
                    $segments[] = ['label' => 'Jam ke-7 dst', 'hours' => round($h3, 2), 'multiplier' => 4.0];
                    $totalMultiplierHours += $h3 * 4.0;
                }
            } else {
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

        $payload = [
            'monthlyWage' => round($monthlyWage, 2),
            'hourlyWage' => round($hourly, 2),
            'hours' => round($hours, 2),
            'dayType' => $normalizedDayType,
            'weeklyWorkDays' => $weeklyWorkDays,
            'segments' => $segments,
            'totalOvertimePay' => round($totalPay, 2),
            'regulationNote' => 'Perhitungan acuan PP No. 35 Tahun 2021 + Kepmenakertrans KEP.102/MEN/VI/2004.',
        ];

        if ($includeRaw) {
            $payload['hourlyWageRaw'] = $hourly;
            $payload['totalMultiplierHoursRaw'] = $totalMultiplierHours;
            $payload['totalOvertimePayRaw'] = $totalPay;
        }

        return $payload;
    }

    private function normalizeDayType(string $dayType): string
    {
        $normalized = strtolower(trim($dayType));

        return match ($normalized) {
            self::DAY_TYPE_WORKDAY => self::DAY_TYPE_WORKDAY,
            self::DAY_TYPE_WEEKLY_REST, 'restday' => self::DAY_TYPE_WEEKLY_REST,
            self::DAY_TYPE_WEEKLY_REST_SHORT, 'restday_short' => self::DAY_TYPE_WEEKLY_REST_SHORT,
            self::DAY_TYPE_PUBLIC_HOLIDAY, 'holiday' => self::DAY_TYPE_PUBLIC_HOLIDAY,
            default => self::DAY_TYPE_WORKDAY,
        };
    }
}
