<?php

namespace App\Http\Controllers\Api\Saas;

use App\Http\Controllers\Controller;
use App\Models\FeatureClassification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FeatureClassificationController extends Controller
{
    /**
     * Canonical feature codes and their intended tier.
     * Mirrors 2026_05_28_000200_backfill_feature_classifications_from_catalog.php.
     */
    private const CATALOG_TIERS = [
        // mvp / core ────────────────────────────────────────────────────────
        'max_employees' => 'mvp',
        'employee_management' => 'mvp',
        'employee_document_center' => 'mvp',
        'employee_lifecycle' => 'mvp',
        'attendance' => 'mvp',
        'attendance_shift_scheduling' => 'mvp',
        'leave_management' => 'mvp',
        'holiday_calendar' => 'mvp',
        'leave_approval_flow' => 'mvp',
        'payroll' => 'mvp',
        'payroll_components' => 'mvp',
        'payroll_thr' => 'mvp',
        'notifications' => 'mvp',
        'trial_billing_dashboard' => 'mvp',
        'tax_governance' => 'mvp',
        'bpjs_governance' => 'mvp',
        // addon ─────────────────────────────────────────────────────────────
        'allowance_governance' => 'addon',
        'spt_masa_pph21' => 'addon',
        'attendance_correction' => 'addon',
        'overtime' => 'addon',
        'calendar_events' => 'addon',
        'promotion' => 'addon',
        'resignation' => 'addon',
        'termination' => 'addon',
        'goal_tracking' => 'addon',
        'performance_goal_tracking' => 'addon',
        'performance' => 'addon',
        'training' => 'addon',
        'ai_assistant' => 'addon',
        'asset_management' => 'addon',
        'tickets' => 'addon',
        'data_privacy' => 'addon',
        'notes' => 'addon',
        'faq' => 'addon',
    ];

    public function index(Request $request): JsonResponse
    {
        if (! $this->isHcmAdmin($request)) {
            return response()->json(['success' => false, 'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.']], 403);
        }

        $data = FeatureClassification::orderBy('feature_code')->get(['id', 'feature_code', 'tier', 'created_at', 'updated_at']);

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function store(Request $request): JsonResponse
    {
        if (! $this->isHcmAdmin($request)) {
            return response()->json(['success' => false, 'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.']], 403);
        }

        $validated = $request->validate([
            'feature_code' => ['required', 'string', 'max:100', Rule::unique('feature_classifications', 'feature_code')],
            'tier' => ['required', 'string', Rule::in(['mvp', 'addon'])],
        ]);

        $entry = FeatureClassification::create($validated);

        return response()->json(['success' => true, 'data' => $entry], 201);
    }

    public function update(Request $request, FeatureClassification $featureClassification): JsonResponse
    {
        if (! $this->isHcmAdmin($request)) {
            return response()->json(['success' => false, 'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.']], 403);
        }

        $validated = $request->validate([
            'tier' => ['required', 'string', Rule::in(['mvp', 'addon'])],
        ]);

        $featureClassification->update($validated);

        return response()->json(['success' => true, 'data' => $featureClassification]);
    }

    public function destroy(Request $request, FeatureClassification $featureClassification): JsonResponse
    {
        if (! $this->isHcmAdmin($request)) {
            return response()->json(['success' => false, 'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.']], 403);
        }

        $featureClassification->delete();

        return response()->json(['success' => true, 'message' => 'Deleted']);
    }

    /**
     * POST /v1/saas/feature-classifications/backfill
     *
     * Idempotent: inserts catalog defaults for any feature code that doesn't
     * already have a row. Existing rows are left untouched so manual overrides
     * made via the UI survive a re-backfill.
     */
    public function backfill(Request $request): JsonResponse
    {
        if (! $this->isHcmAdmin($request)) {
            return response()->json(['success' => false, 'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.']], 403);
        }

        $existing = FeatureClassification::pluck('tier', 'feature_code')->toArray();

        $inserted = 0;

        foreach (self::CATALOG_TIERS as $code => $tier) {
            if (isset($existing[$code])) {
                continue;
            }

            FeatureClassification::create([
                'feature_code' => $code,
                'tier' => $tier,
            ]);

            $inserted++;
        }

        $total = FeatureClassification::count();

        return response()->json([
            'success' => true,
            'data' => [
                'inserted' => $inserted,
                'skipped' => count(self::CATALOG_TIERS) - $inserted,
                'total' => $total,
            ],
        ]);
    }

    private function isHcmAdmin(Request $request): bool
    {
        $user = $request->user();

        return $user ? $user->isGlobalHcmAdmin() : false;
    }
}
