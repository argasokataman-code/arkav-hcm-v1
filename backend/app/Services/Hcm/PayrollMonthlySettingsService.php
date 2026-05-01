<?php

namespace App\Services\Hcm;

use App\Models\CompanySetting;
use App\Models\HolidayCalendar;
use App\Support\WebsiteSettings;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class PayrollMonthlySettingsService
{
    public const PAYDAY_STRATEGY_PREVIOUS_WORKING_DAY = 'previous_working_day';
    public const PAYDAY_STRATEGY_NEXT_WORKING_DAY = 'next_working_day';
    public const PAYDAY_STRATEGY_EXACT_CALENDAR_DAY = 'exact_calendar_day';

    private const DEFAULTS = [
        'paydayDay' => 28,
        'cutoffOffsetDays' => 3,
        'payrollTimezone' => 'UTC',
        'disburseBeforePaydayAllowed' => false,
        'paydayHolidayStrategy' => self::PAYDAY_STRATEGY_PREVIOUS_WORKING_DAY,
    ];

    public function currentSettings(?int $companyId): array
    {
        $defaultTimezone = $this->defaultTimezone();

        if ($companyId === null) {
            return array_merge(self::DEFAULTS, [
                'payrollTimezone' => $defaultTimezone,
            ]);
        }

        $stored = CompanySetting::query()
            ->where('company_id', $companyId)
            ->whereIn('key', [
                'payroll.monthly.payday_day',
                'payroll.monthly.cutoff_offset_days',
                'payroll.monthly.payroll_timezone',
                'payroll.monthly.disburse_before_payday_allowed',
                'payroll.monthly.payday_holiday_strategy',
            ])
            ->pluck('value', 'key');

        $strategy = (string) ($stored->get('payroll.monthly.payday_holiday_strategy') ?: self::DEFAULTS['paydayHolidayStrategy']);
        if (! in_array($strategy, [
            self::PAYDAY_STRATEGY_PREVIOUS_WORKING_DAY,
            self::PAYDAY_STRATEGY_NEXT_WORKING_DAY,
            self::PAYDAY_STRATEGY_EXACT_CALENDAR_DAY,
        ], true)) {
            $strategy = self::DEFAULTS['paydayHolidayStrategy'];
        }

        return [
            'paydayDay' => (int) ($stored->get('payroll.monthly.payday_day') ?: self::DEFAULTS['paydayDay']),
            'cutoffOffsetDays' => (int) ($stored->get('payroll.monthly.cutoff_offset_days') ?: self::DEFAULTS['cutoffOffsetDays']),
            'payrollTimezone' => $this->sanitizeTimezone((string) ($stored->get('payroll.monthly.payroll_timezone') ?: $defaultTimezone), $defaultTimezone),
            'disburseBeforePaydayAllowed' => ((int) ($stored->get('payroll.monthly.disburse_before_payday_allowed') ?: (self::DEFAULTS['disburseBeforePaydayAllowed'] ? '1' : '0'))) === 1,
            'paydayHolidayStrategy' => $strategy,
        ];
    }

    public function snapshotForPeriod(int $periodYear, int $periodMonth, ?int $companyId, ?string $draftDataAsOfDate = null): array
    {
        $settings = $this->currentSettings($companyId);
        $timezone = $settings['payrollTimezone'];
        $periodStart = Carbon::create($periodYear, $periodMonth, 1, 0, 0, 0, $timezone);
        $periodEnd = $periodStart->copy()->endOfMonth();
        $clampedPayday = $periodStart->copy()->day(min((int) $settings['paydayDay'], (int) $periodEnd->day));
        $resolvedPayday = $this->resolvePaydayDateWithStrategy(
            $clampedPayday,
            (string) ($settings['paydayHolidayStrategy'] ?? self::DEFAULTS['paydayHolidayStrategy']),
            $companyId,
        );
        $resolvedCutoff = $resolvedPayday->copy()->subDays((int) $settings['cutoffOffsetDays']);
        $resolvedDraftDataAsOfDate = $draftDataAsOfDate ?: $resolvedCutoff->toDateString();

        return [
            'paydayDay' => (int) $settings['paydayDay'],
            'cutoffOffsetDays' => (int) $settings['cutoffOffsetDays'],
            'payrollTimezone' => $timezone,
            'disburseBeforePaydayAllowed' => (bool) $settings['disburseBeforePaydayAllowed'],
            'paydayHolidayStrategy' => (string) ($settings['paydayHolidayStrategy'] ?? self::DEFAULTS['paydayHolidayStrategy']),
            'resolvedPaydayDate' => $resolvedPayday->toDateString(),
            'resolvedCutoffDate' => $resolvedCutoff->toDateString(),
            'resolutionRule' => 'calendar_day_clamped_to_end_of_month_with_holiday_strategy',
            'draftDataAsOfDate' => $resolvedDraftDataAsOfDate,
            'settingsVersion' => 1,
        ];
    }

    private function resolvePaydayDateWithStrategy(Carbon $basePayday, string $strategy, ?int $companyId): Carbon
    {
        if ($strategy === self::PAYDAY_STRATEGY_EXACT_CALENDAR_DAY) {
            return $basePayday;
        }

        $candidate = $basePayday->copy();
        $guard = 0;
        while ($this->isNonWorkingDate($candidate, $companyId)) {
            if ($strategy === self::PAYDAY_STRATEGY_NEXT_WORKING_DAY) {
                $candidate->addDay();
            } else {
                $candidate->subDay();
            }

            $guard++;
            if ($guard > 31) {
                break;
            }
        }

        return $candidate;
    }

    private function isNonWorkingDate(Carbon $date, ?int $companyId): bool
    {
        if ($date->isWeekend()) {
            return true;
        }

        if (! Schema::hasTable('holiday_calendars')) {
            return false;
        }

        $query = HolidayCalendar::query()->whereDate('date', $date->toDateString());
        if ($companyId !== null) {
            $query->where(function (Builder $inner) use ($companyId): void {
                $inner->where('company_id', $companyId)->orWhereNull('company_id');
            });
        } else {
            $query->whereNull('company_id');
        }

        return $query->exists();
    }

    private function defaultTimezone(): string
    {
        try {
            return WebsiteSettings::localizationTimezone();
        } catch (\Throwable) {
            return (string) config('app.timezone', self::DEFAULTS['payrollTimezone']);
        }
    }

    private function sanitizeTimezone(string $timezone, string $fallback): string
    {
        $timezone = trim($timezone);
        if ($timezone === '') {
            return $fallback;
        }

        return in_array($timezone, timezone_identifiers_list(), true) ? $timezone : $fallback;
    }
}