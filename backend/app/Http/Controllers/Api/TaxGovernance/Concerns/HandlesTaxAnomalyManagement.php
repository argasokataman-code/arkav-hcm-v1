<?php

namespace App\Http\Controllers\Api\TaxGovernance\Concerns;

use App\Models\HcmTaxGovernanceAnomaly;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

trait HandlesTaxAnomalyManagement
{
    public function anomalyRegistry(Request $request): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'tax.governance.anomaly.view_all')) {
            return $response;
        }

        $validated = $request->validate([
            'severity_filter' => ['nullable', Rule::in(['info', 'warning', 'critical'])],
            'anomaly_type_filter' => ['nullable', 'string'],
            'resolved' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 50);

        $query = HcmTaxGovernanceAnomaly::query()
            ->orderByDesc('detected_at');

        if (! empty($validated['severity_filter'])) {
            $query->where('severity', $validated['severity_filter']);
        }

        if (! empty($validated['anomaly_type_filter'])) {
            $query->where('anomaly_type', $validated['anomaly_type_filter']);
        }

        if (isset($validated['resolved'])) {
            if ($validated['resolved']) {
                $query->whereNotNull('resolved_at');
            } else {
                $query->whereNull('resolved_at');
            }
        } else {
            // Default: show unresolved only
            $query->whereNull('resolved_at');
        }

        $anomalies = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'anomalies' => collect($anomalies->items())->map(function (HcmTaxGovernanceAnomaly $anom) {
                    return [
                        'id' => $anom->id,
                        'company_id' => $anom->company_id,
                        'company_name' => optional($anom->company)->name ?? 'Unknown',
                        'anomaly_type' => $anom->anomaly_type,
                        'severity' => $anom->severity,
                        'description' => $anom->description,
                        'affected_policy_id' => $anom->affected_policy_id,
                        'detected_at' => optional($anom->detected_at)->toIso8601String(),
                        'resolved_at' => optional($anom->resolved_at)?->toIso8601String(),
                    ];
                })->values(),
                'meta' => [
                    'page' => $anomalies->currentPage(),
                    'per_page' => $anomalies->perPage(),
                    'total' => $anomalies->total(),
                ],
            ],
        ]);
    }

    public function resolveAnomaly(Request $request, string $anomalyId): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'tax.governance.anomaly.manage')) {
            return $response;
        }

        $anomaly = HcmTaxGovernanceAnomaly::find($anomalyId);
        if (! $anomaly) {
            return $this->errorResponse('ANOMALY_NOT_FOUND', 'Anomaly not found.', 404);
        }

        // Verify tenant access (global admin can resolve any, tenant user can only resolve own)
        $userCompanyId = $this->activeCompanyId($request);
        $isGlobalAdmin = (bool) ($request->user()?->isGlobalHcmAdmin() ?? false);
        if (! $isGlobalAdmin && $anomaly->company_id !== $userCompanyId) {
            return $this->errorResponse('AUTH_FORBIDDEN', 'Cannot resolve anomaly in other tenant.', 403);
        }

        if ($anomaly->resolved_at !== null) {
            return $this->errorResponse('ANOMALY_ALREADY_RESOLVED', 'Anomaly is already resolved.', 422);
        }

        $validated = $request->validate([
            'resolution_note' => ['required', 'string', 'max:1000'],
        ]);

        $actorId = (int) ($request->user()?->id ?? 0) ?: null;

        DB::transaction(function () use ($anomaly, $validated, $actorId): void {
            $evidenceSnapshot = is_array($anomaly->evidence_snapshot) ? $anomaly->evidence_snapshot : [];
            $resolutionLog = is_array($evidenceSnapshot['resolution_audit'] ?? null)
                ? $evidenceSnapshot['resolution_audit']
                : [];
            $resolutionLog[] = [
                'resolved_at' => now()->toIso8601String(),
                'resolved_by_user_id' => $actorId,
                'resolution_note' => $validated['resolution_note'],
            ];

            $anomaly->resolved_at = now();
            $anomaly->resolution_note = $validated['resolution_note'];
            $anomaly->evidence_snapshot = array_merge($evidenceSnapshot, [
                'resolution_audit' => $resolutionLog,
            ]);
            $anomaly->save();
        });

        return response()->json([
            'success' => true,
            'data' => [
                'anomaly_id' => $anomaly->id,
                'resolved_at' => $anomaly->resolved_at->toIso8601String(),
                'resolution_note' => $anomaly->resolution_note,
            ],
        ]);
    }

    public function acknowledgeAnomaly(Request $request, string $anomalyId): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'tax.governance.anomaly.manage')) {
            return $response;
        }

        $anomaly = HcmTaxGovernanceAnomaly::find($anomalyId);
        if (! $anomaly) {
            return $this->errorResponse('ANOMALY_NOT_FOUND', 'Anomaly not found.', 404);
        }

        // Verify tenant access
        $userCompanyId = $this->activeCompanyId($request);
        $isGlobalAdmin = (bool) ($request->user()?->isGlobalHcmAdmin() ?? false);
        if (! $isGlobalAdmin && (int) $anomaly->company_id !== (int) $userCompanyId) {
            return $this->errorResponse('AUTH_FORBIDDEN', 'Cannot acknowledge anomaly in other tenant.', 403);
        }

        $actorId = (int) ($request->user()?->id ?? 0) ?: null;

        // Add acknowledgment to evidence (in JSON column if available, or create event)
        $anomaly->acknowledged_by_user_id = $actorId;
        $anomaly->acknowledged_at = now();
        $anomaly->save();

        return response()->json([
            'success' => true,
            'data' => [
                'anomaly_id' => $anomaly->id,
                'acknowledged_at' => $anomaly->acknowledged_at->toIso8601String(),
                'acknowledged_by_user_id' => $actorId,
            ],
        ]);
    }
}
