<?php

namespace App\Http\Controllers\Api\Attendance;

use App\Http\Controllers\Api\Concerns\EnsuresHcmAdmin;
use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Manages per-company attendance configuration stored in company_settings.
 *
 * RBAC: ensureHcmAdmin (owner, admin, hcm_admin, super_admin)
 *
 * Routes:
 *   GET  /v1/hcm/attendance/settings
 *   PUT  /v1/hcm/attendance/settings
 */
class HcmAttendanceSettingsController extends Controller
{
    use EnsuresHcmAdmin;

    private const CORRECTION_WINDOW_KEY = 'attendance_correction_window_days';
    private const CORRECTION_WINDOW_DEFAULT = 30;

    /**
     * GET /v1/hcm/attendance/settings
     * Returns current attendance settings for the active company.
     */
    public function show(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensureHcmAdmin($request)) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);        if (! $companyId) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'TENANT_REQUIRED', 'message' => 'Active company context is required.'],
            ], 400);
        }

        $windowDays = (int) (CompanySetting::query()
            ->where('company_id', $companyId)
            ->where('key', self::CORRECTION_WINDOW_KEY)
            ->value('value') ?? self::CORRECTION_WINDOW_DEFAULT);

        return response()->json([
            'success' => true,
            'data' => [
                'correctionWindowDays' => $windowDays,
            ],
        ]);
    }

    /**
     * PUT /v1/hcm/attendance/settings
     * Saves attendance settings for the active company.
     */
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
            'correctionWindowDays' => ['required', 'integer', 'min:0', 'max:365'],
        ]);

        CompanySetting::query()->updateOrCreate(
            ['company_id' => $companyId, 'key' => self::CORRECTION_WINDOW_KEY],
            ['value' => (string) $validated['correctionWindowDays'], 'type' => 'integer']
        );

        return response()->json([
            'success' => true,
            'data' => [
                'correctionWindowDays' => (int) $validated['correctionWindowDays'],
            ],
        ]);
    }

    private function activeCompanyId(Request $request): ?int
    {
        $value = $request->attributes->get('activeCompanyId');

        return is_numeric($value) ? (int) $value : null;
    }
}
