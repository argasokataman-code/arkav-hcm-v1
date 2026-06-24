<?php

namespace App\Http\Controllers\Api\Geofence;

use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Http\Controllers\Controller;
use App\Models\Geofence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HcmGeofenceController extends Controller
{
    use ChecksPermissions;

    public function index(Request $request): JsonResponse
    {
        $forbidden = $this->ensureAnyPermission($request, ['attendance.manage', 'attendance.view']);
        if ($forbidden) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->error('TENANT_CONTEXT_REQUIRED', 'Active company context is required.', 422);
        }

        $perPage = (int) ($request->query('perPage', 20));
        $perPage = min($perPage, 100);

        $query = Geofence::query()->where('company_id', $companyId);

        $search = $request->query('search', '');
        if ($search !== '') {
            $query->where('name', 'LIKE', "%{$search}%");
        }

        $total = $query->count();
        $rows = $query->orderBy('name')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $rows->map(fn (Geofence $g) => $this->serialize($g))->values(),
            'meta' => [
                'page' => $rows->currentPage(),
                'perPage' => $rows->perPage(),
                'total' => $total,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'attendance.manage');
        if ($forbidden) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->error('TENANT_CONTEXT_REQUIRED', 'Active company context is required.', 422);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('geofences')->where(fn ($q) => $q->where('company_id', $companyId))],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_meters' => ['required', 'integer', 'min:10', 'max:50000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $geofence = Geofence::query()->create([
            'company_id' => $companyId,
            'name' => $validated['name'],
            'latitude' => (float) $validated['latitude'],
            'longitude' => (float) $validated['longitude'],
            'radius_meters' => (int) $validated['radius_meters'],
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return response()->json(['success' => true, 'data' => $this->serialize($geofence)], 201);
    }

    public function show(Request $request, Geofence $geofence): JsonResponse
    {
        $forbidden = $this->ensureAnyPermission($request, ['attendance.manage', 'attendance.view']);
        if ($forbidden) {
            return $forbidden;
        }

        if (! $this->belongsToCompany($request, $geofence)) {
            return $this->error('NOT_FOUND', 'Geofence not found.', 404);
        }

        return response()->json(['success' => true, 'data' => $this->serialize($geofence)]);
    }

    public function update(Request $request, Geofence $geofence): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'attendance.manage');
        if ($forbidden) {
            return $forbidden;
        }

        if (! $this->belongsToCompany($request, $geofence)) {
            return $this->error('NOT_FOUND', 'Geofence not found.', 404);
        }

        $companyId = $this->activeCompanyId($request);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('geofences')->where(fn ($q) => $q->where('company_id', $companyId))->ignore($geofence->id)],
            'latitude' => ['sometimes', 'required', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'required', 'numeric', 'between:-180,180'],
            'radius_meters' => ['sometimes', 'required', 'integer', 'min:10', 'max:50000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $geofence->update($validated);

        return response()->json(['success' => true, 'data' => $this->serialize($geofence->fresh())]);
    }

    public function destroy(Request $request, Geofence $geofence): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'attendance.manage');
        if ($forbidden) {
            return $forbidden;
        }

        if (! $this->belongsToCompany($request, $geofence)) {
            return $this->error('NOT_FOUND', 'Geofence not found.', 404);
        }

        $geofence->delete();

        return response()->json(['success' => true]);
    }

    private function belongsToCompany(Request $request, Geofence $geofence): bool
    {
        $companyId = $this->activeCompanyId($request);

        return $companyId !== null && (int) $geofence->company_id === (int) $companyId;
    }

    private function activeCompanyId(Request $request): ?int
    {
        $value = $request->attributes->get('activeCompanyId');

        return is_numeric($value) ? (int) $value : null;
    }

    private function serialize(Geofence $geofence): array
    {
        return [
            'id' => $geofence->id,
            'uuid' => $geofence->uuid,
            'company_id' => $geofence->company_id,
            'name' => $geofence->name,
            'latitude' => (float) $geofence->latitude,
            'longitude' => (float) $geofence->longitude,
            'radius_meters' => (int) $geofence->radius_meters,
            'is_active' => (bool) $geofence->is_active,
            'created_at' => $geofence->created_at?->toIso8601String(),
            'updated_at' => $geofence->updated_at?->toIso8601String(),
        ];
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => ['code' => $code, 'message' => $message],
        ], $status);
    }
}
