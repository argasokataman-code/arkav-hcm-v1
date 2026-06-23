<?php

namespace App\Http\Controllers\Api\Attendance;

use App\Http\Controllers\Api\Concerns\EnsuresHcmAdmin;
use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HcmAttendanceSettingsController extends Controller
{
    use EnsuresHcmAdmin;

    private const CORRECTION_WINDOW_KEY = 'attendance_correction_window_days';
    private const DEFAULT_CHECK_IN_TIME_KEY = 'attendance_default_check_in_time';
    private const EARLY_PUNCH_OUT_KEY = 'attendance_early_punch_out_threshold_minutes';
    private const MAX_BREAK_KEY = 'attendance_max_break_minutes';

    private const CORRECTION_WINDOW_DEFAULT = 30;
    private const DEFAULT_CHECK_IN_TIME_DEFAULT = '09:00';
    private const EARLY_PUNCH_OUT_DEFAULT = 240;
    private const MAX_BREAK_DEFAULT = 0;

    public function show(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensureHcmAdmin($request)) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'TENANT_REQUIRED', 'message' => 'Active company context is required.'],
            ], 400);
        }

        $settings = CompanySetting::query()
            ->where('company_id', $companyId)
            ->whereIn('key', [
                self::CORRECTION_WINDOW_KEY,
                self::DEFAULT_CHECK_IN_TIME_KEY,
                self::EARLY_PUNCH_OUT_KEY,
                self::MAX_BREAK_KEY,
            ])
            ->get()
            ->keyBy('key');

        return response()->json([
            'success' => true,
            'data' => [
                'correctionWindowDays' => (int) ($settings->get(self::CORRECTION_WINDOW_KEY)?->value ?? self::CORRECTION_WINDOW_DEFAULT),
                'defaultCheckInTime' => (string) ($settings->get(self::DEFAULT_CHECK_IN_TIME_KEY)?->value ?? self::DEFAULT_CHECK_IN_TIME_DEFAULT),
                'earlyPunchOutThresholdMinutes' => (int) ($settings->get(self::EARLY_PUNCH_OUT_KEY)?->value ?? self::EARLY_PUNCH_OUT_DEFAULT),
                'maxBreakMinutes' => (int) ($settings->get(self::MAX_BREAK_KEY)?->value ?? self::MAX_BREAK_DEFAULT),
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensureHcmAdmin($request)) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'TENANT_REQUIRED', 'message' => 'Active company context is required.'],
            ], 400);
        }

        $validated = $request->validate([
            'correctionWindowDays' => ['nullable', 'integer', 'min:0', 'max:365'],
            'defaultCheckInTime' => ['nullable', 'date_format:H:i'],
            'earlyPunchOutThresholdMinutes' => ['nullable', 'integer', 'min:1', 'max:720'],
            'maxBreakMinutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
        ]);

        $response = [];

        if (array_key_exists('correctionWindowDays', $validated)) {
            CompanySetting::query()->updateOrCreate(
                ['company_id' => $companyId, 'key' => self::CORRECTION_WINDOW_KEY],
                ['value' => (string) $validated['correctionWindowDays'], 'type' => 'integer']
            );
            $response['correctionWindowDays'] = (int) $validated['correctionWindowDays'];
        } else {
            $response['correctionWindowDays'] = (int) (CompanySetting::query()
                ->where('company_id', $companyId)
                ->where('key', self::CORRECTION_WINDOW_KEY)
                ->value('value') ?? self::CORRECTION_WINDOW_DEFAULT);
        }

        if (array_key_exists('defaultCheckInTime', $validated)) {
            CompanySetting::query()->updateOrCreate(
                ['company_id' => $companyId, 'key' => self::DEFAULT_CHECK_IN_TIME_KEY],
                ['value' => $validated['defaultCheckInTime'], 'type' => 'string']
            );
            $response['defaultCheckInTime'] = $validated['defaultCheckInTime'];
        } else {
            $response['defaultCheckInTime'] = (string) (CompanySetting::query()
                ->where('company_id', $companyId)
                ->where('key', self::DEFAULT_CHECK_IN_TIME_KEY)
                ->value('value') ?? self::DEFAULT_CHECK_IN_TIME_DEFAULT);
        }

        if (array_key_exists('earlyPunchOutThresholdMinutes', $validated)) {
            CompanySetting::query()->updateOrCreate(
                ['company_id' => $companyId, 'key' => self::EARLY_PUNCH_OUT_KEY],
                ['value' => (string) $validated['earlyPunchOutThresholdMinutes'], 'type' => 'integer']
            );
            $response['earlyPunchOutThresholdMinutes'] = (int) $validated['earlyPunchOutThresholdMinutes'];
        } else {
            $response['earlyPunchOutThresholdMinutes'] = (int) (CompanySetting::query()
                ->where('company_id', $companyId)
                ->where('key', self::EARLY_PUNCH_OUT_KEY)
                ->value('value') ?? self::EARLY_PUNCH_OUT_DEFAULT);
        }

        if (array_key_exists('maxBreakMinutes', $validated)) {
            CompanySetting::query()->updateOrCreate(
                ['company_id' => $companyId, 'key' => self::MAX_BREAK_KEY],
                ['value' => (string) $validated['maxBreakMinutes'], 'type' => 'integer']
            );
            $response['maxBreakMinutes'] = (int) $validated['maxBreakMinutes'];
        } else {
            $response['maxBreakMinutes'] = (int) (CompanySetting::query()
                ->where('company_id', $companyId)
                ->where('key', self::MAX_BREAK_KEY)
                ->value('value') ?? self::MAX_BREAK_DEFAULT);
        }

        return response()->json([
            'success' => true,
            'data' => $response,
        ]);
    }

    private function activeCompanyId(Request $request): ?int
    {
        $value = $request->attributes->get('activeCompanyId');

        return is_numeric($value) ? (int) $value : null;
    }
}
