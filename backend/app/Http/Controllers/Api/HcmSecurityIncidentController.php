<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Http\Controllers\Controller;
use App\Jobs\SendBreachNotificationToSubjects;
use App\Models\DataBreachIncident;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HcmSecurityIncidentController extends Controller
{
    use ChecksPermissions;

    public function index(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'user_management.manage')) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->errorResponse('TENANT_CONTEXT_REQUIRED', 'Active company context is required.', 422);
        }

        $items = DataBreachIncident::query()
            ->where('company_id', $companyId)
            ->orderByDesc('detected_at')
            ->paginate((int) $request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'user_management.manage')) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->errorResponse('TENANT_CONTEXT_REQUIRED', 'Active company context is required.', 422);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'affected_data_types' => ['nullable', 'array'],
            'affected_data_types.*' => ['string', 'max:100'],
            'affected_subjects_count' => ['nullable', 'integer', 'min:0'],
            'affected_user_uuids' => ['nullable', 'array'],
            'affected_user_uuids.*' => ['string', 'max:64'],
            'detected_at' => ['required', 'date'],
            'reported_to_bssn_at' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in([
                DataBreachIncident::STATUS_DETECTED,
                DataBreachIncident::STATUS_NOTIFIED,
                DataBreachIncident::STATUS_RESOLVED,
            ])],
        ]);

        $actor = $request->user();

        $incident = DataBreachIncident::query()->create([
            'company_id' => $companyId,
            'title' => $validated['title'],
            'description' => $validated['description'],
            'affected_data_types' => $validated['affected_data_types'] ?? [],
            'affected_subjects_count' => (int) ($validated['affected_subjects_count'] ?? 0),
            'affected_user_uuids' => $validated['affected_user_uuids'] ?? [],
            'detected_at' => $validated['detected_at'],
            'reported_to_bssn_at' => $validated['reported_to_bssn_at'] ?? null,
            'status' => $validated['status'] ?? DataBreachIncident::STATUS_DETECTED,
            'created_by_uuid' => (string) ($actor?->uuid ?? ''),
        ]);

        return response()->json([
            'success' => true,
            'data' => $incident,
        ], 201);
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'user_management.manage')) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->errorResponse('TENANT_CONTEXT_REQUIRED', 'Active company context is required.', 422);
        }

        $incident = DataBreachIncident::query()
            ->where('company_id', $companyId)
            ->where('uuid', $uuid)
            ->first();

        if (! $incident) {
            return $this->errorResponse('NOT_FOUND', 'Security incident not found.', 404);
        }

        return response()->json([
            'success' => true,
            'data' => $incident,
        ]);
    }

    public function notifySubjects(Request $request, string $uuid): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'user_management.manage')) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->errorResponse('TENANT_CONTEXT_REQUIRED', 'Active company context is required.', 422);
        }

        $incident = DataBreachIncident::query()
            ->where('company_id', $companyId)
            ->where('uuid', $uuid)
            ->first();

        if (! $incident) {
            return $this->errorResponse('NOT_FOUND', 'Security incident not found.', 404);
        }

        SendBreachNotificationToSubjects::dispatch($incident->id)->afterCommit();

        return response()->json([
            'success' => true,
            'data' => [
                'queued' => true,
                'incident_uuid' => $incident->uuid,
            ],
        ]);
    }

    public function resolve(Request $request, string $uuid): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'user_management.manage')) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->errorResponse('TENANT_CONTEXT_REQUIRED', 'Active company context is required.', 422);
        }

        $incident = DataBreachIncident::query()
            ->where('company_id', $companyId)
            ->where('uuid', $uuid)
            ->first();

        if (! $incident) {
            return $this->errorResponse('NOT_FOUND', 'Security incident not found.', 404);
        }

        $incident->forceFill([
            'status' => DataBreachIncident::STATUS_RESOLVED,
        ])->save();

        return response()->json([
            'success' => true,
            'data' => $incident,
        ]);
    }

    private function errorResponse(string $code, string $message, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], $status);
    }
}
