<?php

namespace App\Services\Hcm;

use App\Models\HcmEmployeeWorkArrangement;
use App\Models\HcmPayrollWorkProfile;
use App\Models\Holiday;
use App\Models\HolidayCalendar;
use App\Models\OvertimeRequest;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class PayrollWorkRuleResolver
{
    /**
     * @return array{dayType:string, weeklyWorkDays:int, arrangementMode:string, source:string, profileId:int|null}
     */
    public function resolveForOvertimeRequest(OvertimeRequest $request, ?int $companyId = null): array
    {
        $workDate = $request->work_date instanceof Carbon
            ? $request->work_date->toDateString()
            : Carbon::parse((string) $request->work_date)->toDateString();

        $arrangement = $this->resolveArrangementForDate((int) $request->user_id, $workDate, $companyId);

        $dayType = $request->day_type
            ? $this->normalizeDayType((string) $request->day_type)
            : ($arrangement['defaultDayType'] ?? null);

        if (! $dayType) {
            $dayType = $this->isHolidayDate($workDate, $companyId)
                ? OvertimePayCalculator::DAY_TYPE_PUBLIC_HOLIDAY
                : OvertimePayCalculator::DAY_TYPE_WORKDAY;
        }

        $weeklyWorkDays = (int) ($request->weekly_work_days ?? 0);
        if ($weeklyWorkDays !== 5 && $weeklyWorkDays !== 6) {
            $weeklyWorkDays = (int) ($arrangement['weeklyWorkDays'] ?? 0);
        }
        if ($weeklyWorkDays !== 5 && $weeklyWorkDays !== 6) {
            $weeklyWorkDays = ($arrangement['arrangementMode'] ?? 'office_hour') === 'shift_worker' ? 6 : 5;
        }

        return [
            'dayType' => $dayType,
            'weeklyWorkDays' => $weeklyWorkDays,
            'arrangementMode' => (string) ($arrangement['arrangementMode'] ?? 'office_hour'),
            'source' => $request->day_type || $request->weekly_work_days
                ? 'request_payload'
                : (string) ($arrangement['source'] ?? 'system_default'),
            'profileId' => $arrangement['profileId'] ?? null,
        ];
    }

    /**
     * @return array{arrangementMode:string, defaultDayType:string|null, weeklyWorkDays:int|null, profileId:int|null, source:string}
     */
    public function resolveArrangementForDate(int $userId, string $workDate, ?int $companyId = null): array
    {
        $date = Carbon::parse($workDate)->toDateString();

        $arrangementQuery = HcmEmployeeWorkArrangement::query()
            ->with('profile')
            ->where('user_id', $userId)
            ->whereDate('effective_from', '<=', $date)
            ->where(function (Builder $query) use ($date): void {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $date);
            })
            ->orderByRaw('company_id IS NULL')
            ->orderByDesc('effective_from')
            ->orderByDesc('id');

        if ($companyId !== null) {
            $arrangementQuery->where(function (Builder $query) use ($companyId): void {
                $query->where('company_id', $companyId)
                    ->orWhereNull('company_id');
            });
        } else {
            $arrangementQuery->whereNull('company_id');
        }

        $arrangement = $arrangementQuery->first();
        if ($arrangement !== null) {
            $profile = $arrangement->profile;

            return [
                'arrangementMode' => (string) ($arrangement->arrangement_mode ?: ($profile?->arrangement_mode ?: 'office_hour')),
                'defaultDayType' => $this->normalizeDayType((string) ($arrangement->default_day_type ?: ($profile?->default_day_type ?: ''))),
                'weeklyWorkDays' => $this->normalizeWeeklyDays($arrangement->weekly_work_days ?? $profile?->weekly_work_days),
                'profileId' => $profile?->id,
                'source' => 'employee_arrangement',
            ];
        }

        $profileQuery = HcmPayrollWorkProfile::query()
            ->where('is_default', true)
            ->orderByRaw('company_id IS NULL')
            ->orderByDesc('id');

        if ($companyId !== null) {
            $profileQuery->where(function (Builder $query) use ($companyId): void {
                $query->where('company_id', $companyId)
                    ->orWhereNull('company_id');
            });
        } else {
            $profileQuery->whereNull('company_id');
        }

        $profile = $profileQuery->first();
        if ($profile !== null) {
            return [
                'arrangementMode' => (string) ($profile->arrangement_mode ?: 'office_hour'),
                'defaultDayType' => $this->normalizeDayType((string) ($profile->default_day_type ?: 'workday')),
                'weeklyWorkDays' => $this->normalizeWeeklyDays($profile->weekly_work_days),
                'profileId' => $profile->id,
                'source' => 'default_profile',
            ];
        }

        return [
            'arrangementMode' => 'office_hour',
            'defaultDayType' => null,
            'weeklyWorkDays' => 5,
            'profileId' => null,
            'source' => 'system_default',
        ];
    }

    private function normalizeWeeklyDays(mixed $value): ?int
    {
        $days = (int) $value;

        return in_array($days, [5, 6], true) ? $days : null;
    }

    private function normalizeDayType(string $value): string
    {
        $normalized = strtolower(trim($value));

        return match ($normalized) {
            OvertimePayCalculator::DAY_TYPE_WORKDAY => OvertimePayCalculator::DAY_TYPE_WORKDAY,
            OvertimePayCalculator::DAY_TYPE_PUBLIC_HOLIDAY, 'holiday' => OvertimePayCalculator::DAY_TYPE_PUBLIC_HOLIDAY,
            OvertimePayCalculator::DAY_TYPE_WEEKLY_REST, 'restday' => OvertimePayCalculator::DAY_TYPE_WEEKLY_REST,
            OvertimePayCalculator::DAY_TYPE_WEEKLY_REST_SHORT, 'restday_short' => OvertimePayCalculator::DAY_TYPE_WEEKLY_REST_SHORT,
            default => OvertimePayCalculator::DAY_TYPE_WORKDAY,
        };
    }

    private function isHolidayDate(string $date, ?int $companyId = null): bool
    {
        if (Schema::hasTable('holiday_calendars')) {
            $holidayCalendarQuery = HolidayCalendar::query()->whereDate('date', $date);
            if ($companyId !== null) {
                $holidayCalendarQuery->where(function (Builder $query) use ($companyId): void {
                    $query->where('company_id', $companyId)->orWhereNull('company_id');
                });
            } else {
                $holidayCalendarQuery->whereNull('company_id');
            }

            if ($holidayCalendarQuery->exists()) {
                return true;
            }
        }

        if (Schema::hasTable('holidays')) {
            return Holiday::query()
                ->where('is_active', true)
                ->whereDate('holiday_date', $date)
                ->exists();
        }

        return false;
    }
}
