<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HcmPayrollSettingsController extends Controller
{
    use ChecksPermissions;

    private const SETTING_KEYS = [
        'payroll.monthly.payday_day',
        'payroll.monthly.cutoff_offset_days',
        'payroll.monthly.payroll_timezone',
        'payroll.monthly.disburse_before_payday_allowed',
        'payroll.monthly.payday_holiday_strategy',
    ];

    private const DEFAULTS = [
        'payroll.monthly.payday_day' => '28',
        'payroll.monthly.cutoff_offset_days' => '3',
        'payroll.monthly.payroll_timezone' => 'Asia/Jakarta',
        'payroll.monthly.disburse_before_payday_allowed' => '0',
        'payroll.monthly.payday_holiday_strategy' => 'previous_working_day',
    ];

    public function show(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'settings.manage')) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->apiError('TENANT_REQUIRED', 'Active company context is required.', 400);
        }

        return $this->apiSuccess($this->currentSettings($companyId));
    }

    public function update(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'settings.manage')) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->apiError('TENANT_REQUIRED', 'Active company context is required.', 400);
        }

        $validated = $request->validate([
            'paydayDay' => ['nullable', 'integer', 'min:1', 'max:31'],
            'cutoffOffsetDays' => ['nullable', 'integer', 'min:0', 'max:15'],
            'payrollTimezone' => ['nullable', 'timezone:all'],
            'disburseBeforePaydayAllowed' => ['nullable', 'boolean'],
            'paydayHolidayStrategy' => ['nullable', 'string', 'in:previous_working_day,next_working_day,exact_calendar_day'],
        ]);

        $map = [
            'paydayDay' => 'payroll.monthly.payday_day',
            'cutoffOffsetDays' => 'payroll.monthly.cutoff_offset_days',
            'payrollTimezone' => 'payroll.monthly.payroll_timezone',
            'disburseBeforePaydayAllowed' => 'payroll.monthly.disburse_before_payday_allowed',
            'paydayHolidayStrategy' => 'payroll.monthly.payday_holiday_strategy',
        ];

        foreach ($map as $requestKey => $settingKey) {
            if (! array_key_exists($requestKey, $validated)) {
                continue;
            }

            $value = $validated[$requestKey];
            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            }

            CompanySetting::query()->updateOrCreate(
                ['company_id' => $companyId, 'key' => $settingKey],
                [
                    'value' => $value === null ? '' : (string) $value,
                    'type' => is_int($validated[$requestKey] ?? null) ? 'integer' : (is_bool($validated[$requestKey] ?? null) ? 'boolean' : 'string'),
                ],
            );
        }

        return $this->apiSuccess($this->currentSettings($companyId), 'Payroll settings saved.');
    }

    private function currentSettings(int $companyId): array
    {
        $stored = CompanySetting::query()
            ->where('company_id', $companyId)
            ->whereIn('key', self::SETTING_KEYS)
            ->pluck('value', 'key');

        return [
            'paydayDay' => (int) $stored->get('payroll.monthly.payday_day', self::DEFAULTS['payroll.monthly.payday_day']),
            'cutoffOffsetDays' => (int) $stored->get('payroll.monthly.cutoff_offset_days', self::DEFAULTS['payroll.monthly.cutoff_offset_days']),
            'payrollTimezone' => (string) $stored->get('payroll.monthly.payroll_timezone', self::DEFAULTS['payroll.monthly.payroll_timezone']),
            'disburseBeforePaydayAllowed' => (bool) ((int) $stored->get('payroll.monthly.disburse_before_payday_allowed', self::DEFAULTS['payroll.monthly.disburse_before_payday_allowed'])),
            'paydayHolidayStrategy' => (string) $stored->get('payroll.monthly.payday_holiday_strategy', self::DEFAULTS['payroll.monthly.payday_holiday_strategy']),
        ];
    }

    private function apiSuccess(array $data = [], ?string $message = null, int $status = 200): JsonResponse
    {
        $payload = ['success' => true, 'data' => $data];
        if ($message !== null && $message !== '') {
            $payload['message'] = $message;
        }

        return response()->json($payload, $status);
    }

    private function apiError(string $code, string $message, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => ['code' => $code, 'message' => $message],
        ], $status);
    }
}