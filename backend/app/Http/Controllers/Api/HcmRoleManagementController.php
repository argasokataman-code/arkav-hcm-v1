<?php

namespace App\Http\Controllers\Api;

use App\Models\HcmRole;
use App\Models\HcmPermission;
use App\Models\HcmRolePermission;
use App\Services\HcmRbacService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class HcmRoleManagementController extends Controller
{
    protected HcmRbacService $rbacService;

    public function __construct(HcmRbacService $rbacService)
    {
        $this->rbacService = $rbacService;
    }

    /**
     * Get all roles for a company
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $companyId = $this->getCompanyIdFromRequest($request);

        // Check permission
        if (!$this->rbacService->userHasPermission($user, 'role.view', $companyId)) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        $roles = HcmRole::with(['permissions', 'company'])
            ->when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->when(!$companyId && $this->rbacService->isGlobalAdmin($user), fn($q) => $q->platform())
            ->get();

        return response()->json([
            'success' => true,
            'data' => $roles,
        ]);
    }

    /**
     * Create a new role (Super admin only for setup)
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        // Only super admin can create roles
        if (!$this->rbacService->isGlobalAdmin($user)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SUPER_USER_REQUIRED',
                    'message' => 'Only super administrators can create roles.'
                ]
            ], 403);
        }

        $validated = $request->validate([
            'company_id' => 'nullable|uuid|exists:companies,uuid',
            'code' => ['required', 'string', 'max:80', Rule::unique('hcm_roles', 'code')->where('company_id', $request->company_id)],
            'name' => 'required|string|max:150',
            'description' => 'nullable|string|max:2000',
            'permission_codes' => 'array',
            'permission_codes.*' => 'string|exists:hcm_permissions,code',
        ]);

        DB::transaction(function () use ($validated) {
            $role = HcmRole::create([
                'company_id' => $validated['company_id'] ?? null,
                'code' => $validated['code'],
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'status' => 'active',
                'is_system' => false,
                'created_by' => auth()->id(),
            ]);

            // Sync permissions if provided
            if (!empty($validated['permission_codes'])) {
                $this->rbacService->syncRolePermissions(
                    $role,
                    $validated['permission_codes'],
                    $validated['company_id'] ?? null
                );
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Role created successfully',
        ], 201);
    }

    /**
     * Update role permissions (Super admin only)
     */
    public function syncPermissions(Request $request, HcmRole $role): JsonResponse
    {
        $user = $request->user();

        // Only super admin can modify role permissions
        if (!$this->rbacService->isGlobalAdmin($user)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SUPER_USER_REQUIRED',
                    'message' => 'Only super administrators can modify role permissions.'
                ]
            ], 403);
        }

        $validated = $request->validate([
            'permission_codes' => 'required|array',
            'permission_codes.*' => 'string|exists:hcm_permissions,code',
            'company_id' => 'nullable|uuid|exists:companies,uuid',
        ]);

        $companyId = $validated['company_id'] ?? $role->company_id;

        $this->rbacService->syncRolePermissions(
            $role,
            $validated['permission_codes'],
            $companyId
        );

        return response()->json([
            'success' => true,
            'message' => 'Role permissions updated successfully',
        ]);
    }

    /**
     * Delete role (Super admin only)
     */
    public function destroy(Request $request, HcmRole $role): JsonResponse
    {
        $user = $request->user();

        // Only super admin can delete roles
        if (!$this->rbacService->isGlobalAdmin($user)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SUPER_USER_REQUIRED',
                    'message' => 'Only super administrators can delete roles.'
                ]
            ], 403);
        }

        // Prevent deletion of system roles
        if ($role->is_system) {
            return response()->json([
                'success' => false,
                'error' => ['message' => 'System roles cannot be deleted']
            ], 422);
        }

        $role->delete();

        return response()->json([
            'success' => true,
            'message' => 'Role deleted successfully',
        ]);
    }

    /**
     * Get company ID from request
     */
    protected function getCompanyIdFromRequest(Request $request): ?int
    {
        return $request->header('X-Company-ID')
            ? (int) $request->header('X-Company-ID')
            : $this->rbacService->getUserCompanyId($request->user());
    }
}