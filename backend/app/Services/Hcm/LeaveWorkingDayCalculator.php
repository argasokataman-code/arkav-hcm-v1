<?php

namespace App\Services\Hcm;

use App\Models\Holiday;
use App\Models\HolidayCalendar;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Schema;

class LeaveWorkingDayCalculator
{
    /**
     * @return array{totalDays: float, workingDates: array<int,string>, excludedDates: array<int,array{date:string,reason:string}>}
     */
    public function calculate(
        string|Carbon $startDate,
        string|Carbon $endDate,
        ?int $companyId = null,
        bool $isHalfDay = false,
        bool $excludeWeekends = true,
        bool $excludeHolidays = true
    ): array {
        $start = $startDate instanceof Carbon ? $startDate->copy()->startOfDay() : Carbon::parse($startDate)->startOfDay();
        $end = $endDate instanceof Carbon ? $endDate->copy()->startOfDay() : Carbon::parse($endDate)->startOfDay();

        if ($end->lt($start)) {
            [$start, $end] = [$end, $start];
        }

        $holidayMap = [];
        if ($excludeHolidays) {
            if (Schema::hasTable('holiday_calendars')) {
                $holidayRows = HolidayCalendar::query()
                    ->whereDate('date', '>=', $start->toDateString())
                    ->whereDate('date', '<=', $end->toDateString())
                    ->when($companyId !== null, function ($q) use ($companyId) {
                        $q->where(function ($inner) use ($companyId) {
                            $inner->whereNull('company_id')->orWhere('company_id', $companyId);
                        });
                    }, fn ($q) => $q->whereNull('company_id'))
                    ->get(['date', 'name']);

                foreach ($holidayRows as $row) {
                    $holidayMap[$row->date->toDateString()] = (string) ($row->name ?? 'Holiday');
                }
            }

            if (Schema::hasTable('holidays')) {
                $legacyRows = Holiday::query()
                    ->where('is_active', true)
                    ->whereDate('holiday_date', '>=', $start->toDateString())
                    ->whereDate('holiday_date', '<=', $end->toDateString())
                    ->get(['holiday_date', 'title']);

                foreach ($legacyRows as $row) {
                    $dateKey = $row->holiday_date->toDateString();
                    if (! isset($holidayMap[$dateKey])) {
                        $holidayMap[$dateKey] = (string) ($row->title ?? 'Holiday');
                    }
                }
            }
        }

        $workingDates = [];
        $excludedDates = [];

        $period = CarbonPeriod::create($start, $end);
        foreach ($period as $date) {
            $dateKey = $date->toDateString();

            if ($excludeWeekends && $date->isWeekend()) {
                $excludedDates[] = ['date' => $dateKey, 'reason' => 'weekend'];

                continue;
            }

            if ($excludeHolidays && array_key_exists($dateKey, $holidayMap)) {
                $excludedDates[] = ['date' => $dateKey, 'reason' => 'holiday: '.$holidayMap[$dateKey]];

                continue;
            }

            $workingDates[] = $dateKey;
        }

        $totalDays = (float) count($workingDates);
        if ($isHalfDay) {
            $totalDays = $totalDays > 0 ? 0.5 : 0.0;
            if (count($workingDates) > 1) {
                $workingDates = [$workingDates[0]];
            }
        }

        return [
            'totalDays' => $totalDays,
            'workingDates' => $workingDates,
            'excludedDates' => $excludedDates,
        ];
    }
}
