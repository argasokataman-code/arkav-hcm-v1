<?php

namespace App\Http\Controllers\Api\Billing;

use App\Http\Controllers\Controller;
use App\Jobs\ApplySubscriptionChangeJob;
use App\Jobs\NotifySubscriptionChangeApproverJob;
use App\Jobs\NotifyTenantSubscriptionChangeDecisionJob;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\HcmSubscriptionChangeRequest;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
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
    private function isPrimarySuperAdminCodeOne(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $primaryEmail = strtolower(trim((string) config('hcm.admin_email', 'qa.login@example.com')));
        $userEmail = strtolower(trim((string) ($user->email ?? '')));

        return $userEmail !== '' && $userEmail === $primaryEmail;
    }

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

    private function assertTenantCanViewHistory(Request $request, int $companyId): ?JsonResponse
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

        $isActiveMember = CompanyUser::query()
            ->where('company_id', $companyId)
            ->where('user_id', (int) $user->id)
            ->where('status', 'active')
            ->exists();

        if ($isActiveMember) {
            return null;
        }

        return response()->json([
            'success' => false,
            'error' => ['code' => 'FORBIDDEN', 'message' => 'Only active tenant members can view subscription change history.'],
        ], 403);
    }

    private function resolveTargetPackage(?string $toPackageUuid): ?Package
    {
        if ($toPackageUuid === null || $toPackageUuid === '') {
            return null;
        }

        return Package::query()->where('uuid', $toPackageUuid)->first();
    }

    private function ensureTargetPackageActive(?Package $target, string $action): ?JsonResponse
    {
        if ($action === HcmSubscriptionChangeRequest::ACTION_CANCEL) {
            return null;
        }

        if (! $target) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PACKAGE_NOT_FOUND',
                    'message' => 'Target package not found.',
                ],
            ], 404);
        }

        if ((string) $target->status !== 'active') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PACKAGE_NOT_ACTIVE',
                    'message' => 'Only active packages can be requested for subscription change.',
                ],
            ], 422);
        }

        if ((string) $target->code === 'trial') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TRIAL_PACKAGE_NOT_ALLOWED',
                    'message' => 'Trial package cannot be used as a subscription change target.',
                ],
            ], 422);
        }

        return null;
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

    private function resolveReferenceSubscription(int $companyId): ?Subscription
    {
        $active = Subscription::activeForCompany($companyId);
        if ($active) {
            $active->loadMissing('package');

            return $active;
        }

        $latest = Subscription::query()
            ->with('package')
            ->where('company_id', $companyId)
            ->whereNotIn('status', ['cancelled'])
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->first();

        return $latest;
    }

    /**
     * @return array{flags: array<int, string>, details: array<string, mixed>, summary: string}
     */
    private function buildBillingAnomalySnapshot(int $companyId, ?Subscription $reference): array
    {
        $flags = [];
        $details = [];

        if ($reference && ! in_array((string) $reference->status, ['active', 'trial'], true)) {
            $flags[] = 'SUBSCRIPTION_NOT_ACTIVE';
            $details['subscription_status'] = (string) $reference->status;
        }

        $invoice = Invoice::query()
            ->where('company_id', $companyId)
            ->where('is_paid', false)
            ->whereIn('status', ['draft', 'sent', 'overdue'])
            ->orderBy('due_date')
            ->orderByDesc('id')
            ->first();

        if ($invoice) {
            $details['invoice_uuid'] = (string) $invoice->uuid;
            $details['invoice_number'] = (string) $invoice->invoice_number;
            $details['invoice_due_date'] = optional($invoice->due_date)->toDateString();

            $amountDue = (float) ($invoice->amount_due ?? 0);
            $paidAmount = (float) (Payment::query()
                ->where('invoice_id', $invoice->id)
                ->where('status', 'completed')
                ->sum('amount') ?? 0);
            $remaining = max(round($amountDue - $paidAmount, 2), 0.0);

            $details['invoice_amount_due'] = $amountDue;
            $details['invoice_amount_paid'] = $paidAmount;
            $details['invoice_remaining_due'] = $remaining;

            if ($invoice->due_date && $invoice->due_date->isPast() && $remaining > 0) {
                $flags[] = 'BILLING_OVERDUE_INVOICE';
            }

            if ($paidAmount > 0 && $remaining > 0) {
                $flags[] = 'BILLING_PARTIAL_PAYMENT';
            } elseif ($paidAmount <= 0 && $remaining > 0) {
                $flags[] = 'BILLING_UNPAID_INVOICE';
            }
        }

        $messages = [];
        foreach (array_values(array_unique($flags)) as $flag) {
            if ($flag === 'SUBSCRIPTION_NOT_ACTIVE') {
                $messages[] = 'Subscription saat ini tidak aktif, downgrade tetap dapat diajukan.';
            } elseif ($flag === 'BILLING_OVERDUE_INVOICE') {
                $messages[] = 'Invoice tenant sudah melewati jatuh tempo.';
            } elseif ($flag === 'BILLING_PARTIAL_PAYMENT') {
                $messages[] = 'Pembayaran parsial terdeteksi: masih ada sisa tagihan invoice.';
            } elseif ($flag === 'BILLING_UNPAID_INVOICE') {
                $messages[] = 'Invoice tenant belum dibayar.';
            }
        }

        return [
            'flags' => array_values(array_unique($flags)),
            'details' => $details,
            'summary' => implode(' ', $messages),
        ];
    }

    private function buildPreview(?Subscription $subscription, ?Package $target, string $action, array $anomalySnapshot = []): array
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

        $baseNote = $action === HcmSubscriptionChangeRequest::ACTION_CANCEL
            ? 'Subscription akan dihentikan pada akhir periode aktif.'
            : ($action === HcmSubscriptionChangeRequest::ACTION_UPGRADE
                ? 'Upgrade akan aktif setelah request disetujui admin platform.'
                : 'Downgrade akan aktif mulai siklus penagihan berikutnya.');

        $anomalySummary = trim((string) ($anomalySnapshot['summary'] ?? ''));
        $note = $anomalySummary !== ''
            ? ($baseNote . ' Catatan anomali: ' . $anomalySummary)
            : $baseNote;

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
            'notes' => $note,
            'anomaly_flags' => (array) ($anomalySnapshot['flags'] ?? []),
            'anomaly_details' => (array) ($anomalySnapshot['details'] ?? []),
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

        $subscription = $this->resolveReferenceSubscription($companyId);
        $target = $this->resolveTargetPackage($validated['to_package_uuid'] ?? null);
        if ($block = $this->ensureTargetPackageActive($target, (string) $validated['action'])) {
            return $block;
        }

        $currentPackage = $subscription?->package;
        if ($target && $currentPackage && $target->uuid === $currentPackage->uuid) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SAME_PACKAGE_NOT_ALLOWED',
                    'message' => 'Paket target tidak boleh sama dengan paket aktif saat ini.',
                ],
            ], 422);
        }

        $action = $this->determineAction($currentPackage, $target, $validated['action']);
        if ($action === HcmSubscriptionChangeRequest::ACTION_CANCEL) {
            $target = null;
        }

        $anomalySnapshot = $this->buildBillingAnomalySnapshot($companyId, $subscription);

        return response()->json([
            'success' => true,
            'data' => [
                'preview' => $this->buildPreview($subscription, $target, $action, $anomalySnapshot),
                'has_active_subscription' => $subscription !== null
                    && in_array((string) $subscription->status, ['active', 'trial'], true),
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

        $subscription = $this->resolveReferenceSubscription($companyId);
        $target = $this->resolveTargetPackage($validated['to_package_uuid'] ?? null);
        if ($block = $this->ensureTargetPackageActive($target, (string) $validated['action'])) {
            return $block;
        }

        $currentPackage = $subscription?->package;
        if ($target && $currentPackage && $target->uuid === $currentPackage->uuid) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SAME_PACKAGE_NOT_ALLOWED',
                    'message' => 'Paket target tidak boleh sama dengan paket aktif saat ini.',
                ],
            ], 422);
        }

        $action = $this->determineAction($currentPackage, $target, $validated['action']);
        if ($action === HcmSubscriptionChangeRequest::ACTION_CANCEL) {
            $target = null;
        }

        $anomalySnapshot = $this->buildBillingAnomalySnapshot($companyId, $subscription);
        $preview = $this->buildPreview($subscription, $target, $action, $anomalySnapshot);

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

        if ($block = $this->assertTenantCanViewHistory($request, $companyId)) {
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
        if (! $this->isPrimarySuperAdminCodeOne($request->user())) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PRIMARY_SUPER_ADMIN_REQUIRED',
                    'message' => 'Only primary super admin code 1 can access subscription change queue.',
                ],
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
        if (! $this->isPrimarySuperAdminCodeOne($request->user())) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PRIMARY_SUPER_ADMIN_REQUIRED',
                    'message' => 'Only primary super admin code 1 can approve subscription changes.',
                ],
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

        NotifyTenantSubscriptionChangeDecisionJob::dispatchAfterResponse($record->id);

        if ($record->action === HcmSubscriptionChangeRequest::ACTION_UPGRADE) {
            return response()->json([
                'success' => true,
                'data' => $this->formatRequest($record),
            ]);
        }

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
        if (! $this->isPrimarySuperAdminCodeOne($request->user())) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PRIMARY_SUPER_ADMIN_REQUIRED',
                    'message' => 'Only primary super admin code 1 can reject subscription changes.',
                ],
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

        NotifyTenantSubscriptionChangeDecisionJob::dispatchAfterResponse($record->id);

        return response()->json([
            'success' => true,
            'data' => $this->formatRequest($record->refresh()),
        ]);
    }

    /**
     * Tenant requests early activation of an approved downgrade/cancel request
     * without waiting for effective_at. User explicitly accepts all risks.
     *
     * POST /v1/hcm/subscriptions/change-requests/{id}/activate-early
     */
    public function activateEarly(Request $request, string $id): JsonResponse
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
            'risk_accepted' => 'required|boolean|accepted',
        ]);

        $company = Company::query()->where('id', $companyId)->first();
        if (! $company) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'COMPANY_NOT_FOUND', 'message' => 'Company not found.'],
            ], 404);
        }

        $record = HcmSubscriptionChangeRequest::query()
            ->where('id', $id)
            ->where('company_uuid', $company->uuid)
            ->first();

        if (! $record) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'CHANGE_REQUEST_NOT_FOUND', 'message' => 'Change request not found.'],
            ], 404);
        }

        if ($record->status !== HcmSubscriptionChangeRequest::STATUS_APPROVED) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CHANGE_REQUEST_NOT_APPROVED',
                    'message' => 'Hanya request yang sudah disetujui yang bisa diaktifkan lebih awal.',
                ],
            ], 422);
        }

        if ($record->action === HcmSubscriptionChangeRequest::ACTION_UPGRADE) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UPGRADE_CANNOT_EARLY_ACTIVATE',
                    'message' => 'Upgrade tidak mendukung aktivasi awal lewat endpoint ini.',
                ],
            ], 422);
        }

        // Run job synchronously so the response already reflects the new state.
        ApplySubscriptionChangeJob::dispatchSync($record->id);

        return response()->json([
            'success' => true,
            'data' => $this->formatRequest($record->refresh()),
            'message' => 'Aktivasi awal berhasil. Invoice baru telah diterbitkan — silakan selesaikan pembayaran untuk mulai menggunakan paket baru.',
        ]);
    }

    private function formatRequest(HcmSubscriptionChangeRequest $record): array
    {
        $record->load('company');
        $company = $record->company;

        return [
            'id' => $record->id,
            'company_uuid' => $record->company_uuid,
            'company_code' => $company?->code ?? '',
            'company_name' => $company?->name ?? '',
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
