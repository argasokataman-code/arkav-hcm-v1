<?php

namespace App\Services\Hcm;

use App\Models\AttendanceRecord;
use App\Models\HcmPayrollPeriod;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Support\HcmFeatureFlags;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Menghitung penyesuaian payroll untuk integrasi cuti & hari libur (H3).
 *
 * Hanya aktif bila feature flag `payroll.leave_integration_enabled` = true
 * (global atau per-tenant via `company_settings`).
 *
 * Output dipakai {@see \App\Support\PayrollDraftBuilder} untuk membuat dua
 * jenis line tambahan:
 *  - Deduction `potongan_cuti_unpaid` bila ada approved unpaid leave di bulan
 *    periode.
 *  - Addition `tunjangan_kerja_libur` bila ada attendance records pada tanggal
 *    libur (sumber `holiday_calendars` / `holidays`).
 */
class PayrollLeaveHolidayAdjuster
{
    public function __construct(private readonly LeaveWorkingDayCalculator $calculator) {}

    /**
     * @return array{
     *   enabled:bool,
     *   workingDaysInMonth:int,
     *   dailyRate:float,
     *   unpaidLeaveDays:float,
     *   unpaidLeaveAmount:float,
     *   unpaidLeaveRequestIds:array<int,int>,
     *   holidayWorkDays:int,
     *   holidayWorkAmount:float,
     *   holidayDates:array<int,string>,
     *   holidayWorkMultiplier:float
     * }
     */
    public function adjust(User $user, HcmPayrollPeriod $period, ?int $companyId, float $base, float $fixed, ?Carbon $asOf = null): array
    {
        $empty = [
            'enabled' => false,
            'workingDaysInMonth' => 0,
            'dailyRate' => 0.0,
            'unpaidLeaveDays' => 0.0,
            'unpaidLeaveAmount' => 0.0,
            'unpaidLeaveRequestIds' => [],
            'holidayWorkDays' => 0,
            'holidayWorkAmount' => 0.0,
            'holidayDates' => [],
            'holidayWorkMultiplier' => (float) config('hcm.payroll.holiday_work_multiplier', 2.0),
        ];

        $enabled = (bool) HcmFeatureFlags::forCompany($companyId, 'payroll.leave_integration_enabled', false);
        if (! $enabled) {
            return $empty;
        }

        $start = Carbon::create($period->period_year, $period->period_month, 1)->startOfDay();
        $periodEnd = (clone $start)->endOfMonth();
        $eventEnd = $periodEnd->copy();
        if ($asOf instanceof Carbon && $asOf->lt($eventEnd)) {
            $eventEnd = $asOf->copy()->endOfDay();
        }

        $monthCalc = $this->calculator->calculate($start, $periodEnd, $companyId, false, true, true);
        $workingDays = (int) $monthCalc['totalDays'];
        if ($workingDays <= 0) {
            return array_merge($empty, ['enabled' => true]);
        }

        $gross = max(0.0, $base + $fixed);
        $dailyRate = $gross / $workingDays;

        // --- Unpaid leave (approved, overlap dengan periode) ---
        $unpaidDays = 0.0;
        $unpaidIds = [];
        $leaveRows = LeaveRequest::query()
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->where(function ($q) use ($start, $eventEnd): void {
                $q->whereBetween('date_from', [$start->toDateString(), $eventEnd->toDateString()])
                    ->orWhereBetween('date_to', [$start->toDateString(), $eventEnd->toDateString()])
                    ->orWhere(function ($q2) use ($start, $eventEnd): void {
                        $q2->where('date_from', '<=', $start->toDateString())
                            ->where('date_to', '>=', $eventEnd->toDateString());
                    });
            })
            ->when(
                $companyId !== null,
                fn ($q) => $q->where(function ($q2) use ($companyId): void {
                    $q2->where('company_id', $companyId)->orWhereNull('company_id');
                })
            )
            ->get();

        foreach ($leaveRows as $row) {
            if (! self::isUnpaidType((string) $row->leave_type)) {
                continue;
            }
            $from = Carbon::parse($row->date_from)->startOfDay();
            $to = Carbon::parse($row->date_to)->startOfDay();
            if ($from->lt($start)) {
                $from = $start->copy();
            }
            if ($to->gt($eventEnd)) {
                $to = $eventEnd->copy();
            }
            if ($to->lt($from)) {
                continue;
            }
            $leaveCalc = $this->calculator->calculate($from, $to, $companyId, false, true, true);
            $unpaidDays += (float) $leaveCalc['totalDays'];
            $unpaidIds[] = (int) $row->id;
        }

        // --- Holiday work (attendance pada tanggal libur) ---
        $holidayOnly = [];
        foreach ($monthCalc['excludedDates'] ?? [] as $ex) {
            if (str_starts_with((string) ($ex['reason'] ?? ''), 'holiday')) {
                $holidayOnly[(string) $ex['date']] = true;
            }
        }

        $holidayDates = [];
        if (! empty($holidayOnly) && Schema::hasTable('attendance_records')) {
            $rows = AttendanceRecord::query()
                ->where('user_id', $user->id)
                ->whereNotNull('check_in_at')
                ->whereBetween('work_date', [$start->toDateString(), $eventEnd->toDateString()])
                ->when(
                    $companyId !== null,
                    fn ($q) => $q->where(function ($q2) use ($companyId): void {
                        $q2->where('company_id', $companyId)->orWhereNull('company_id');
                    })
                )
                ->pluck('work_date');

            foreach ($rows as $raw) {
                $d = Carbon::parse($raw)->toDateString();
                if (isset($holidayOnly[$d])) {
                    $holidayDates[$d] = true;
                }
            }
        }

        $multiplier = (float) config('hcm.payroll.holiday_work_multiplier', 2.0);
        $unpaidAmount = round($dailyRate * $unpaidDays, 2);
        $holidayWorkDays = count($holidayDates);
        $holidayAmount = round($dailyRate * $holidayWorkDays * $multiplier, 2);

        return [
            'enabled' => true,
            'workingDaysInMonth' => $workingDays,
            'dailyRate' => round($dailyRate, 2),
            'unpaidLeaveDays' => round($unpaidDays, 2),
            'unpaidLeaveAmount' => $unpaidAmount,
            'unpaidLeaveRequestIds' => $unpaidIds,
            'holidayWorkDays' => $holidayWorkDays,
            'holidayWorkAmount' => $holidayAmount,
            'holidayDates' => array_keys($holidayDates),
            'holidayWorkMultiplier' => $multiplier,
        ];
    }

    private static function isUnpaidType(string $leaveType): bool
    {
        $t = strtolower(trim($leaveType));
        if ($t === '') {
            return false;
        }

        return $t === 'unpaid'
            || str_starts_with($t, 'unpaid_')
            || str_contains($t, '_unpaid')
            || str_contains($t, 'no_pay')
            || str_contains($t, 'tanpa_gaji');
    }
}
