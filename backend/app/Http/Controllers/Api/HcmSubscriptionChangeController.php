<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ApplySubscriptionChangeJob;
use App\Jobs\NotifySubscriptionChangeApproverJob;
use App\Models\Company;
use App\Models\HcmSubscriptionChangeRequest;
use App\Models\Package;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Tenant-facing subscription plan change requests (F4).
 *
 * Endpoints:
 * - POST /v1/hcm/subscriptions/preview-change  — dry-run: hitung selisih
 *   paket & effective date, tidak menulis apa pun.
 * - POST /v1/hcm/subscriptions/change-plan     — simpan request pending
 *   untuk approval super-admin.
 * - POST /v1/hcm/subscriptions/cancel-change   — cancel request pending
 *   milik tenant sendiri.
 * - GET  /v1/hcm/subscriptions/change-requests — list request untuk tenant.
 * - POST /v1/saas/subscription-change-requests/{id}/approve  — super-admin.
 * - POST /v1/saas/subscription-change-requests/{id}/reject   — super-admin.
 */
class HcmSubscriptionChangeController extends Controller
{
    private function activeCompanyId(Request $request): ?int
    {
        $value = $request->attributes->get('activeCompanyId');

        return is_numeric($value) ? (int) $value : null;
    }

    private function assertTenantOwnerOrAdmin(Request $request, int $companyId): ?JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Authentication required.'],
            ], 401);
        }

        if ($user->isGlobalHcmAdmin() || $user->isHcmAdminForCompany($companyId)) {
            return null;
        }

        return response()->json([
            'success' => false,
            'error' => ['code' => 'FORBIDDEN', 'message' => 'Only tenant admin or owner can change the plan.'],
        ], 403);
    }

    private function resolveTargetPackage(?string $toPackageUuid): ?Package
    {
        if ($toPackageUuid === null || $toPackageUuid === '') {
            return null;
        }

        return Package::query()->where('uuid', $toPackageUuid)->first();
    }

    private function determineAction(?Package $current, ?Package $target, string $requested): string
    {
        if ($requested === HcmSubscriptionChangeRequest::ACTION_CANCEL) {
            return HcmSubscriptionChangeRequest::ACTION_CANCEL;
        }

        $currentPrice = $current ? (float) $current->monthly_price : 0.0;
        $targetPrice = $target ? (float) $target->monthly_price : 0.0;

        if ($targetPrice > $currentPrice) {
            return HcmSubscriptionChangeRequest::ACTION_UPGRADE;
        }

        return HcmSubscriptionChangeRequest::ACTION_DOWNGRADE;
    }

    private function buildPreview(?Subscription $subscription, ?Package $target, string $action): array
    {
        $currentPackage = $subscription?->package;
        $billingCycle = $subscription?->billing_cycle ?? 'monthly';

        $currentPrice = $this->priceForCycle($currentPackage, $billingCycle);
        $targetPrice = $this->priceForCycle($target, $billingCycle);

        if ($action === HcmSubscriptionChangeRequest::ACTION_UPGRADE) {
            $effectiveAt = Carbon::now();
        } else {
            $effectiveAt = $subscription?->ends_at
                ? Carbon::parse($subscription->ends_at)
                : Carbon::now()->addMonth();
        }

        return [
            'action' => $action,
            'billing_cycle' => $billingCycle,
            'from_package' => $currentPackage ? [
                'uuid' => $currentPackage->uuid,
                'code' => $currentPackage->code,
                'name' => $currentPackage->name,
                'price' => $currentPrice,
            ] : null,
            'to_package' => $target ? [
                'uuid' => $target->uuid,
                'code' => $target->code,
                'name' => $target->name,
                'price' => $targetPrice,
            ] : null,
            'price_delta' => round($targetPrice - $currentPrice, 2),
            'effective_at' => $effectiveAt->toIso8601String(),
            'notes' => $action === HcmSubscriptionChangeRequest::ACTION_CANCEL
                ? 'Subscription akan dihentikan pada akhir periode aktif.'
                : ($action === HcmSubscriptionChangeRequest::ACTION_UPGRADE
                    ? 'Upgrade akan aktif setelah request disetujui admin platform.'
                    : 'Downgrade akan aktif mulai siklus penagihan berikutnya.'),
        ];
    }

    private function priceForCycle(?Package $package, string $cycle): float
    {
        if (! $package) {
            return 0.0;
        }

        return (float) ($cycle === 'yearly' ? $package->yearly_price : $package->monthly_price);
    }

    public function preview(Request $request): JsonResponse
    {
        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'TENANT_CONTEXT_REQUIRED', 'message' => 'Active company context required.'],
            ], 400);
        }

        if ($block = $this->assertTenantOwnerOrAdmin($request, $companyId)) {
            return $block;
        }

        $validated = $request->validate([
            'action' => 'required|string|in:upgrade,downgrade,cancel',
            'to_package_uuid' => 'required_unless:action,cancel|nullable|uuid|exists:packages,uuid',
        ]);

        $subscription = Subscription::activeForCompany($companyId);
        $target = $this->resolveTargetPackage($validated['to_package_uuid'] ?? null);
        $action = $this->determineAction($subscription?->package, $target, $validated['action']);
        if ($action === HcmSubscriptionChangeRequest::ACTION_CANCEL) {
            $target = null;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'preview' => $this->buildPreview($subscription, $target, $action),
                'has_active_subscription' => $subscription !== null,
            ],
        ]);
    }

    public function changePlan(Request $request): JsonResponse
    {
        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'TENANT_CONTEXT_REQUIRED', 'message' => 'Active company context required.'],
            ], 400);
        }

        if ($block = $this->assertTenantOwnerOrAdmin($request, $companyId)) {
            return $block;
        }

        $validated = $request->validate([
            'action' => 'required|string|in:upgrade,downgrade,cancel',
            'to_package_uuid' => 'required_unless:action,cancel|nullable|uuid|exists:packages,uuid',
            'notes' => 'nullable|string|max:500',
        ]);

        $company = Company::query()->where('id', $companyId)->first();
        if (! $company) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'COMPANY_NOT_FOUND', 'message' => 'Active company not found.'],
            ], 404);
        }

        $user = $request->user();

        // Single pending guard per company.
        $existingPending = HcmSubscriptionChangeRequest::query()
            ->where('company_uuid', $company->uuid)
            ->where('status', HcmSubscriptionChangeRequest::STATUS_PENDING)
            ->first();

        if ($existingPending) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CHANGE_REQUEST_PENDING',
                    'message' => 'A pending subscription change request already exists.',
                    'meta' => ['pending_id' => $existingPending->id],
                ],
            ], 409);
        }

        $subscription = Subscription::activeForCompany($companyId);
        $target = $this->resolveTargetPackage($validated['to_package_uuid'] ?? null);
        $action = $this->determineAction($subscription?->package, $target, $validated['action']);
        if ($action === HcmSubscriptionChangeRequest::ACTION_CANCEL) {
            $target = null;
        }

        $preview = $this->buildPreview($subscription, $target, $action);

        $record = DB::transaction(function () use ($company, $user, $subscription, $target, $action, $preview, $validated) {
            return HcmSubscriptionChangeRequest::create([
                'id' => (string) Str::uuid(),
                'company_uuid' => $company->uuid,
                'user_uuid' => $user->uuid,
                'current_subscription_uuid' => $subscription?->uuid,
                'from_package_uuid' => $subscription?->package?->uuid,
                'to_package_uuid' => $target?->uuid,
                'action' => $action,
                'status' => HcmSubscriptionChangeRequest::STATUS_PENDING,
                'preview' => $preview,
                'notes' => $validated['notes'] ?? null,
                'effective_at' => $preview['effective_at'],
            ]);
        });

        NotifySubscriptionChangeApproverJob::dispatchAfterResponse($record->id);

        return response()->json([
            'success' => true,
            'data' => $this->formatRequest($record),
        ], 201);
    }

    public function cancelChange(Request $request): JsonResponse
    {
        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'TENANT_CONTEXT_REQUIRED', 'message' => 'Active company context required.'],
            ], 400);
        }

        if ($block = $this->assertTenantOwnerOrAdmin($request, $companyId)) {
            return $block;
        }

        $validated = $request->validate([
            'id' => 'required|uuid|exists:hcm_subscription_change_requests,id',
        ]);

        $company = Company::query()->where('id', $companyId)->first();
        $record = HcmSubscriptionChangeRequest::query()
            ->where('id', $validated['id'])
            ->where('company_uuid', $company?->uuid)
            ->first();

        if (! $record) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'CHANGE_REQUEST_NOT_FOUND', 'message' => 'Change request not found.'],
            ], 404);
        }

        if ($record->status !== HcmSubscriptionChangeRequest::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CHANGE_REQUEST_NOT_PENDING',
                    'message' => 'Only pending requests can be cancelled.',
                ],
            ], 422);
        }

        $record->update([
            'status' => HcmSubscriptionChangeRequest::STATUS_CANCELLED,
            'decided_at' => now(),
            'decided_by_user_uuid' => $request->user()->uuid,
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->formatRequest($record->refresh()),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'TENANT_CONTEXT_REQUIRED', 'message' => 'Active company context required.'],
            ], 400);
        }

        if ($block = $this->assertTenantOwnerOrAdmin($request, $companyId)) {
            return $block;
        }

        $company = Company::query()->where('id', $companyId)->first();
        $rows = HcmSubscriptionChangeRequest::query()
            ->where('company_uuid', $company?->uuid)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $rows->map(fn ($r) => $this->formatRequest($r))->values(),
        ]);
    }

    public function listAllForAdmin(Request $request): JsonResponse
    {
        if (! $request->user()?->isGlobalHcmAdmin()) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $status = trim((string) $request->query('status', ''));
        $query = HcmSubscriptionChangeRequest::query();
        if ($status !== '') {
            $query->where('status', $status);
        }

        $rows = $query->orderByDesc('created_at')->limit(100)->get();

        return response()->json([
            'success' => true,
            'data' => $rows->map(fn ($r) => $this->formatRequest($r))->values(),
        ]);
    }

    public function approve(Request $request, string $id): JsonResponse
    {
        if (! $request->user()?->isGlobalHcmAdmin()) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $record = HcmSubscriptionChangeRequest::query()->where('id', $id)->first();
        if (! $record) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'CHANGE_REQUEST_NOT_FOUND', 'message' => 'Change request not found.'],
            ], 404);
        }

        if ($record->status !== HcmSubscriptionChangeRequest::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CHANGE_REQUEST_NOT_PENDING',
                    'message' => 'Only pending requests can be approved.',
                ],
            ], 422);
        }

        DB::transaction(function () use ($record, $request): void {
            $record->update([
                'status' => HcmSubscriptionChangeRequest::STATUS_APPROVED,
                'decided_at' => now(),
                'decided_by_user_uuid' => $request->user()->uuid,
            ]);
        });

        $record = $record->refresh();

        $effectiveAt = $record->effective_at ? Carbon::parse($record->effective_at) : now();
        if ($effectiveAt->lte(now())) {
            ApplySubscriptionChangeJob::dispatchSync($record->id);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatRequest($record->refresh()),
        ]);
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        if (! $request->user()?->isGlobalHcmAdmin()) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $record = HcmSubscriptionChangeRequest::query()->where('id', $id)->first();
        if (! $record) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'CHANGE_REQUEST_NOT_FOUND', 'message' => 'Change request not found.'],
            ], 404);
        }

        if ($record->status !== HcmSubscriptionChangeRequest::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CHANGE_REQUEST_NOT_PENDING',
                    'message' => 'Only pending requests can be rejected.',
                ],
            ], 422);
        }

        $record->update([
            'status' => HcmSubscriptionChangeRequest::STATUS_REJECTED,
            'decided_at' => now(),
            'decided_by_user_uuid' => $request->user()->uuid,
            'notes' => (string) ($request->input('notes', $record->notes)),
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->formatRequest($record->refresh()),
        ]);
    }

    private function formatRequest(HcmSubscriptionChangeRequest $record): array
    {
        return [
            'id' => $record->id,
            'company_uuid' => $record->company_uuid,
            'user_uuid' => $record->user_uuid,
            'action' => $record->action,
            'status' => $record->status,
            'from_package_uuid' => $record->from_package_uuid,
            'to_package_uuid' => $record->to_package_uuid,
            'preview' => $record->preview,
            'notes' => $record->notes,
            'effective_at' => optional($record->effective_at)->toIso8601String(),
            'decided_at' => optional($record->decided_at)->toIso8601String(),
            'applied_at' => optional($record->applied_at)->toIso8601String(),
            'created_at' => optional($record->created_at)->toIso8601String(),
        ];
    }
}
