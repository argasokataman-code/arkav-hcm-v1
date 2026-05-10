<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\PayrollSettingsSnapshot;
use App\Models\PayrollSettingsAuditLog;
use App\Models\User;
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

        // Capture old settings before any changes
        $oldSettings = $this->currentSettings($companyId);
        $userId = auth()->id();
        $ipAddress = $request->ip();

        foreach ($map as $requestKey => $settingKey) {
            if (! array_key_exists($requestKey, $validated)) {
                continue;
            }

            $value = $validated[$requestKey];
            $oldValue = CompanySetting::query()
                ->where('company_id', $companyId)
                ->where('key', $settingKey)
                ->value('value');

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

            // Create audit log entry for this change
            if ((string) $oldValue !== (string) $value) {
                PayrollSettingsAuditLog::create([
                    'company_id' => $companyId,
                    'user_id' => $userId,
                    'action' => 'update',
                    'setting_key' => $settingKey,
                    'old_value' => $oldValue,
                    'new_value' => $value === null ? '' : (string) $value,
                    'ip_address' => $ipAddress,
                ]);
            }
        }

        // Capture new settings after all changes and create snapshot
        $newSettings = $this->currentSettings($companyId);
        $this->captureSettingsSnapshot($companyId, $userId, $newSettings);

        return $this->apiSuccess($newSettings, 'Payroll settings saved.');
    }

    /**
     * Get settings change history/audit trail
     */
    public function history(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'settings.manage')) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->apiError('TENANT_REQUIRED', 'Active company context is required.', 400);
        }

        $limit = (int) $request->query('limit', 50);
        $offset = (int) $request->query('offset', 0);

        $logs = PayrollSettingsAuditLog::query()
            ->where('company_id', $companyId)
            ->orderBy('changed_at', 'desc')
            ->limit($limit)
            ->offset($offset)
            ->get();

        $total = PayrollSettingsAuditLog::query()
            ->where('company_id', $companyId)
            ->count();

        $data = $logs->map(function (PayrollSettingsAuditLog $log) {
            $changedBy = $log->changedBy;
            return [
                'id' => $log->id,
                'uuid' => $log->uuid,
                'changedAt' => $log->changed_at->toIso8601String(),
                'changedByUserId' => $log->user_id,
                'changedByUserName' => $changedBy ? $changedBy->name : 'Unknown',
                'action' => $log->action,
                'settingKey' => $log->setting_key,
                'oldValue' => $log->old_value,
                'newValue' => $log->new_value,
                'ipAddress' => $log->ip_address,
            ];
        });

        return $this->apiSuccess([
            'logs' => $data,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    /**
     * Capture a snapshot of current payroll settings (for governance/audit trail)
     */
    private function captureSettingsSnapshot(int $companyId, ?int $userId, array $currentSettings): void
    {
        // Get company UUID for the snapshot
        $company = Company::query()->select('uuid')->find($companyId);
        if (! $company) {
            return; // Company not found, skip snapshot
        }

        // Get user UUID if userId provided
        $userUuid = null;
        if ($userId) {
            $user = User::query()->select('uuid')->find($userId);
            $userUuid = $user?->uuid;
        }

        $latestSnapshot = PayrollSettingsSnapshot::query()
            ->where('company_uuid', $company->uuid)
            ->orderBy('snapshot_version', 'desc')
            ->first();

        $nextVersion = ($latestSnapshot?->snapshot_version ?? 0) + 1;

        PayrollSettingsSnapshot::create([
            'company_uuid' => $company->uuid,
            'snapshot_version' => $nextVersion,
            'user_uuid' => $userUuid,
            'settings_data' => $currentSettings,
            'changed_at' => now(),
        ]);
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