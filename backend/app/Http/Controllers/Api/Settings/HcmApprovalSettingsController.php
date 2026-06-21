<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Http\Controllers\Api\Concerns\EnsuresHcmAdmin;
use App\Http\Controllers\Controller;
use App\Models\CompanyUser;
use App\Services\ApprovalConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Manages per-company approval flow configuration.
 *
 * RBAC: Admin-only (owner/admin role or global admin).
 * Endpoints:
 *   GET  /v1/hcm/approval-settings               → list all module configs
 *   PUT  /v1/hcm/approval-settings/{module}       → upsert config for a module
 *   GET  /v1/hcm/approval-settings/eligible-approvers → list eligible approvers for UI
 */
class HcmApprovalSettingsController extends Controller
{
    use ChecksPermissions, EnsuresHcmAdmin;

    private const SUPPORTED_MODULES = ['leave', 'expense', 'offer', 'overtime', 'resignation', 'termination'];

    public function __construct(
        private readonly ApprovalConfigService $approvalConfigService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $guard = $this->ensureHcmAdmin($request);
        if ($guard) {
            return $guard;
        }

        $companyId = (int) ($request->attributes->get('activeCompanyId') ?? 0);
        if ($companyId <= 0) {
            return response()->json(['success' => false, 'error' => ['code' => 'TENANT_REQUIRED', 'message' => 'Company context required.']], 400);
        }

        $result = [];
        foreach (self::SUPPORTED_MODULES as $module) {
            $config = $this->approvalConfigService->getConfigForModule($companyId, $module);
            $result[$module] = $config ? [
                'module' => $module,
                'approvalMode' => $config->approval_mode,
                'isActive' => $config->is_active,
                'approvers' => $config->approvers->map(fn ($a) => [
                    'userId' => $a->approver_user_id,
                    'userUuid' => (string) ($a->approver_user_uuid ?? ''),
                    'name' => (string) ($a->approverUser?->name ?? '—'),
                    'email' => (string) ($a->approverUser?->email ?? '—'),
                    'sequenceOrder' => $a->sequence_order,
                ])->values(),
            ] : [
                'module' => $module,
                'approvalMode' => 'simultaneous',
                'isActive' => false,
                'approvers' => [],
            ];
        }

        return response()->json(['success' => true, 'data' => $result]);
    }

    public function update(Request $request, string $module): JsonResponse
    {
        $guard = $this->ensureHcmAdmin($request);
        if ($guard) {
            return $guard;
        }

        if (! in_array($module, self::SUPPORTED_MODULES, true)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'INVALID_MODULE', 'message' => 'Unsupported approval module.'],
            ], 422);
        }

        $companyId = (int) ($request->attributes->get('activeCompanyId') ?? 0);
        if ($companyId <= 0) {
            return response()->json(['success' => false, 'error' => ['code' => 'TENANT_REQUIRED', 'message' => 'Company context required.']], 400);
        }

        $validated = $request->validate([
            'approvalMode' => ['required', Rule::in(['sequence', 'simultaneous'])],
            'approverUserIds' => ['required', 'array', 'min:1', 'max:10'],
            'approverUserIds.*' => ['required', 'integer', 'exists:users,id'],
        ]);

        // Security: verify all approvers are active members of this company (multi-tenant isolation)
        $memberIds = CompanyUser::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->whereIn('user_id', $validated['approverUserIds'])
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $outsiders = array_diff(array_map('intval', $validated['approverUserIds']), $memberIds);
        if (! empty($outsiders)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'APPROVER_NOT_IN_COMPANY', 'message' => 'One or more selected approvers do not belong to this company.'],
            ], 422);
        }

        $config = $this->approvalConfigService->upsertConfig(
            $companyId,
            $module,
            $validated['approvalMode'],
            $validated['approverUserIds']
        );

        return response()->json([
            'success' => true,
            'data' => [
                'module' => $config->module,
                'approvalMode' => $config->approval_mode,
                'isActive' => $config->is_active,
                'approvers' => $config->approvers->map(fn ($a) => [
                    'userId' => $a->approver_user_id,
                    'name' => (string) ($a->approverUser?->name ?? '—'),
                    'sequenceOrder' => $a->sequence_order,
                ])->values(),
            ],
        ]);
    }

    public function eligibleApprovers(Request $request): JsonResponse
    {
        $guard = $this->ensureHcmAdmin($request);
        if ($guard) {
            return $guard;
        }

        $companyId = (int) ($request->attributes->get('activeCompanyId') ?? 0);
        if ($companyId <= 0) {
            return response()->json(['success' => false, 'error' => ['code' => 'TENANT_REQUIRED', 'message' => 'Company context required.']], 400);
        }

        $search = (string) ($request->query('q', ''));
        $approvers = $this->approvalConfigService->getEligibleApprovers($companyId, $search);

        return response()->json(['success' => true, 'data' => $approvers]);
    }
}
