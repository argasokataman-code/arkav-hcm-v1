<?php

namespace App\Http\Controllers\Api\Saas;

use App\Events\SubscriptionCreated;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\SubscriptionEvent;
use App\Services\CompanyStatusSynchronizer;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly CompanyStatusSynchronizer $companyStatusSynchronizer
    )
    {
    }

    /**
     * GET /v1/saas/subscriptions
     * List subscriptions (admin, with filtering)
     */
    public function index(Request $request): JsonResponse
    {
        if (! $this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $query = Subscription::with(['company', 'package']);

        $status = trim((string) $request->get('status', ''));
        $companyId = (int) $request->get('company_id', 0);
        $planCode = trim((string) $request->get('plan_code', ''));
        $billingCycle = trim((string) $request->get('billing_cycle', ''));
        $search = trim((string) $request->get('search', ''));

        // Filter by status
        if ($status !== '') {
            $query->where('status', $status);
        }

        // Filter by company_id
        if ($companyId > 0) {
            $query->where('company_id', $companyId);
        }

        // Filter by plan_code
        if ($planCode !== '') {
            $query->where('plan_code', $planCode);
        }

        // Filter by billing_cycle
        if ($billingCycle !== '') {
            $query->where('billing_cycle', $billingCycle);
        }

        if ($search !== '') {
            $query->where(function ($subQuery) use ($search) {
                $subQuery->where('plan_code', 'like', '%' . $search . '%')
                    ->orWhereHas('company', function ($companyQuery) use ($search) {
                        $companyQuery->where('name', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('package', function ($packageQuery) use ($search) {
                        $packageQuery->where('name', 'like', '%' . $search . '%')
                            ->orWhere('code', 'like', '%' . $search . '%');
                    });
            });
        }

        $perPage = (int) $request->query('per_page', 15);
        $perPage = max(1, min($perPage, 100));

        $subscriptions = $query->latest('created_at')->paginate($perPage);

        $items = collect($subscriptions->items())
            ->map(fn (Subscription $subscription) => $this->formatSubscription($subscription))
            ->values();

        return response()->json([
            'success' => true,
            'data' => $items,
            'pagination' => [
                'total' => $subscriptions->total(),
                'per_page' => $subscriptions->perPage(),
                'current_page' => $subscriptions->currentPage(),
                'last_page' => $subscriptions->lastPage(),
            ],
        ]);
    }

    /**
     * POST /v1/saas/subscriptions
     * Create subscription (admin only)
     */
    public function store(Request $request): JsonResponse
    {
        if (! $this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $validated = $request->validate([
            'company_id' => 'required|uuid|exists:companies,uuid',
            'package_uuid' => 'required|uuid|exists:packages,uuid',
            'status' => 'required|in:active,trial,pending_payment,inactive,expired,cancelled,suspended',
            'starts_at' => 'required|date',
            'ends_at' => 'required_if:status,active,trial,pending_payment|nullable|date|after:starts_at',
            'trial_ends_at' => 'nullable|date|required_if:status,trial|after:starts_at',
            'auto_renew' => 'boolean',
            'billing_cycle' => 'required|in:monthly,yearly',
            'amount' => 'nullable|numeric|min:0',
        ]);

        if ($validated['status'] !== 'trial') {
            $validated['trial_ends_at'] = null;
        } else {
            $trialErr = $this->validateTrialEndsWithinSubscription(
                $validated['starts_at'],
                $validated['ends_at'],
                $validated['trial_ends_at']
            );
            if ($trialErr !== null) {
                return $trialErr;
            }
        }

        // API contract accepts company UUID while subscriptions table stores numeric company_id.
        $company = Company::query()->where('uuid', $validated['company_id'])->firstOrFail();
        $validated['company_id'] = $company->id;

        // Get package to denormalize plan_code and calculate amount
        $package = Package::query()->where('uuid', $validated['package_uuid'])->firstOrFail();
        if ($gate = $this->ensurePackageAssignableForStatuses($package, $validated['status'])) {
            return $gate;
        }
        $validated['plan_code'] = $package->code;

        // Calculate amount if not provided
        if (!isset($validated['amount']) || $validated['amount'] === null) {
            if ($validated['billing_cycle'] === 'yearly') {
                $validated['amount'] = $package->yearly_price;
            } else {
                $validated['amount'] = $package->monthly_price;
            }
        }

        if ($this->isActiveOrTrialStatus((string) $validated['status'])) {
            if ($guard = $this->ensureNoConcurrentActiveOrTrial((int) $validated['company_id'])) {
                return $guard;
            }
        }

        $subscription = Subscription::create($validated);
        SubscriptionCreated::dispatch((int) $subscription->id, (int) ($request->user()?->id ?? 0));
        $subscription->load('company', 'package');

        return response()->json([
            'success' => true,
            'data' => $this->formatSubscription($subscription),
        ], 201);
    }

    /**
     * GET /v1/saas/subscriptions/{id}
     * Get subscription details
     */
    public function show(Request $request, Subscription $subscription): JsonResponse
    {
        if (! $this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $subscription->load('company', 'package');

        return response()->json([
            'success' => true,
            'data' => $this->formatSubscription($subscription),
        ]);
    }

    /**
     * PUT /v1/saas/subscriptions/{id}
     * Update subscription (admin only)
     */
    public function update(Request $request, Subscription $subscription): JsonResponse
    {
        if (! $this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $validated = $request->validate([
            'package_uuid' => 'sometimes|uuid|exists:packages,uuid',
            'status' => 'sometimes|in:active,trial,pending_payment,inactive,expired,cancelled,suspended',
            'starts_at' => 'sometimes|date',
            'ends_at' => 'nullable|date',
            'trial_ends_at' => 'nullable|date',
            'auto_renew' => 'sometimes|boolean',
            'billing_cycle' => 'sometimes|in:monthly,yearly',
            'amount' => 'nullable|numeric|min:0',
        ]);

        $mergedStatus = $validated['status'] ?? $subscription->status;
        $mergedStarts = $validated['starts_at'] ?? $subscription->starts_at?->toDateString();
        $mergedEnds = array_key_exists('ends_at', $validated)
            ? $validated['ends_at']
            : $subscription->ends_at?->toDateString();
        $mergedTrial = array_key_exists('trial_ends_at', $validated)
            ? $validated['trial_ends_at']
            : $subscription->trial_ends_at?->toDateString();

        if ($mergedStatus === 'pending_payment') {
            $validated['trial_ends_at'] = null;
            $mergedTrial = null;
        }

        if ($mergedStatus === 'trial') {
            if ($mergedTrial === null || $mergedTrial === '') {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'trial_ends_at is required when status is trial.',
                    ],
                ], 422);
            }
            $trialErr = $this->validateTrialEndsWithinSubscription($mergedStarts, $mergedEnds, $mergedTrial);
            if ($trialErr !== null) {
                return $trialErr;
            }
        }

        if (in_array($mergedStatus, ['active', 'trial', 'pending_payment'], true)) {
            $effectiveEndsAt = array_key_exists('ends_at', $validated)
                ? $validated['ends_at']
                : $subscription->ends_at;

            if ($effectiveEndsAt === null) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'ends_at is required when status is active, trial, or pending_payment.',
                    ],
                ], 422);
            }
        }

        $effectivePackageUuid = (string) ($validated['package_uuid'] ?? $subscription->package_uuid);
        $effectivePackage = Package::query()->where('uuid', $effectivePackageUuid)->firstOrFail();
        if ($gate = $this->ensurePackageAssignableForStatuses($effectivePackage, $mergedStatus)) {
            return $gate;
        }

        if ($this->isActiveOrTrialStatus((string) $mergedStatus)) {
            if ($guard = $this->ensureNoConcurrentActiveOrTrial((int) $subscription->company_id, (int) $subscription->id)) {
                return $guard;
            }
        }

        // If package changed, update plan_code and sync amount to catalog prices
        if (isset($validated['package_uuid'])) {
            $package = Package::query()->where('uuid', $validated['package_uuid'])->firstOrFail();
            $validated['plan_code'] = $package->code;
            $billing = $validated['billing_cycle'] ?? $subscription->billing_cycle;
            $validated['amount'] = $billing === 'yearly'
                ? $package->yearly_price
                : $package->monthly_price;
        }

        $wasSuspended = $subscription->status === 'suspended';
        $isReactivation = $wasSuspended && $mergedStatus === 'active';

        if ($isReactivation) {
            $validated['suspended_at'] = null;
            $validated['suspension_reason'] = null;
            $validated['grace_started_at'] = null;
            $validated['grace_ends_at'] = null;
        }

        DB::transaction(function () use ($subscription, $validated, $isReactivation, $request): void {
            $subscription->update($validated);
            $this->companyStatusSynchronizer->syncFromSubscription($subscription->fresh('company'));

            if ($isReactivation) {
                $this->recordSubscriptionEvent(
                    $request,
                    $subscription,
                    'resumed',
                    'SUBSCRIPTION_REACTIVATED_MANUAL_UPDATE',
                    'Subscription reactivated by admin via subscription update endpoint.',
                    [
                        'source' => 'subscription_update',
                        'from_status' => 'suspended',
                        'to_status' => 'active',
                    ]
                );
            }
        });

        if ($isReactivation) {
            $this->notificationService->notifySubscriptionReactivated($subscription->fresh(['company', 'package']));
        }

        $subscription->load('company', 'package');

        return response()->json([
            'success' => true,
            'data' => $this->formatSubscription($subscription),
        ]);
    }

    /**
     * DELETE /v1/saas/subscriptions/{id}
     * Cancel/delete subscription (admin only)
     */
    public function destroy(Request $request, Subscription $subscription): JsonResponse
    {
        if (! $this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'SUBSCRIPTION_DELETE_DISABLED',
                'message' => 'Hard delete is disabled for subscriptions. Use cancel to end the subscription lifecycle.',
            ],
        ], 409);
    }

    /**
     * POST /v1/saas/subscriptions/{id}/renew
     * Renew subscription (admin)
     */
    public function renew(Request $request, Subscription $subscription): JsonResponse
    {
        if (! $this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        if ($subscription->status === 'pending_payment') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SUBSCRIPTION_INVALID_STATE',
                    'message' => 'Cannot renew a subscription that is still awaiting payment. Activate it via paid invoice or update status.',
                ],
            ], 422);
        }

        $validated = $request->validate([
            'ends_at' => 'required|date|after:now',
        ]);

        if ($guard = $this->ensureNoConcurrentActiveOrTrial((int) $subscription->company_id, (int) $subscription->id)) {
            return $guard;
        }

        $wasSuspended = $subscription->status === 'suspended';

        DB::transaction(function () use ($subscription, $validated, $wasSuspended, $request): void {
            $subscription->update([
                'status' => 'active',
                'starts_at' => now(),
                'ends_at' => $validated['ends_at'],
                'trial_ends_at' => null,
                'grace_started_at' => null,
                'grace_ends_at' => null,
                'suspended_at' => null,
                'suspension_reason' => null,
            ]);

            $this->companyStatusSynchronizer->syncFromSubscription($subscription->fresh('company'));

            $this->recordSubscriptionEvent(
                $request,
                $subscription,
                'renewed',
                'SUBSCRIPTION_MANUAL_RENEWED',
                'Subscription renewed manually by admin.',
                [
                    'source' => 'subscription_renew',
                    'from_status' => $wasSuspended ? 'suspended' : $subscription->status,
                    'to_status' => 'active',
                    'renewed_ends_at' => $validated['ends_at'],
                ]
            );

            if ($wasSuspended) {
                $this->recordSubscriptionEvent(
                    $request,
                    $subscription,
                    'resumed',
                    'SUBSCRIPTION_REACTIVATED_MANUAL_RENEW',
                    'Subscription reactivated by admin via renew endpoint.',
                    [
                        'source' => 'subscription_renew',
                        'from_status' => 'suspended',
                        'to_status' => 'active',
                    ]
                );
            }
        });

        if ($wasSuspended) {
            $this->notificationService->notifySubscriptionReactivated($subscription->fresh(['company', 'package']));
        }

        $subscription->load('company', 'package');

        return response()->json([
            'success' => true,
            'data' => $this->formatSubscription($subscription),
        ]);
    }

    /**
     * Active catalog packages only for entitlements that depend on a sellable package.
     */
    private function ensurePackageAssignableForStatuses(Package $package, string $status): ?JsonResponse
    {
        if (! in_array($status, ['active', 'trial', 'pending_payment'], true)) {
            return null;
        }

        if ($package->status !== 'active') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PACKAGE_NOT_ACTIVE',
                    'message' => 'Only packages with status "active" can be used for active, trial, or pending_payment subscriptions.',
                ],
            ], 422);
        }

        return null;
    }

    /**
     * Ensure trial_ends_at is strictly after starts_at and not after subscription ends_at (when provided).
     */
    private function validateTrialEndsWithinSubscription(?string $startsAt, ?string $endsAt, ?string $trialEndsAt): ?JsonResponse
    {
        if ($trialEndsAt === null || $trialEndsAt === '') {
            return null;
        }

        $trial = Carbon::parse($trialEndsAt)->startOfDay();

        if ($startsAt) {
            $start = Carbon::parse($startsAt)->startOfDay();
            if ($trial <= $start) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'trial_ends_at must be after starts_at.',
                    ],
                ], 422);
            }
        }

        if ($endsAt) {
            $end = Carbon::parse($endsAt)->startOfDay();
            if ($trial > $end) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'trial_ends_at must be on or before ends_at.',
                    ],
                ], 422);
            }
        }

        return null;
    }

    private function isActiveOrTrialStatus(string $status): bool
    {
        return in_array($status, ['active', 'trial'], true);
    }

    private function ensureNoConcurrentActiveOrTrial(int $companyId, ?int $exceptSubscriptionId = null): ?JsonResponse
    {
        $query = Subscription::query()
            ->where('company_id', $companyId)
            ->whereIn('status', ['active', 'trial'])
            ->where(function ($builder): void {
                $builder->whereNull('ends_at')
                    ->orWhere('ends_at', '>', now());
            });

        if ($exceptSubscriptionId !== null && $exceptSubscriptionId > 0) {
            $query->where('id', '!=', $exceptSubscriptionId);
        }

        $conflict = $query->first();
        if (! $conflict) {
            return null;
        }

        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'ACTIVE_SUBSCRIPTION_ALREADY_EXISTS',
                'message' => 'Company already has an active or trial subscription. Update the existing subscription instead of creating another active/trial record.',
            ],
        ], 422);
    }

    private function recordSubscriptionEvent(
        Request $request,
        Subscription $subscription,
        string $eventType,
        string $reasonCode,
        string $reasonMessage,
        array $payload = []
    ): void {
        $subscription->loadMissing('company');

        $auditPayload = array_merge([
            'source' => 'manual_admin_action',
            'actor_user_id' => $request->user()?->id,
        ], $payload);

        SubscriptionEvent::query()->create([
            'company_id' => $subscription->company_id,
            'company_uuid' => $subscription->company?->uuid,
            'subscription_id' => $subscription->id,
            'subscription_uuid' => $subscription->uuid,
            'event_type' => $eventType,
            'reason_code' => $reasonCode,
            'reason_message' => mb_substr($reasonMessage, 0, 255),
            'payload' => $auditPayload,
            'occurred_at' => now(),
            'created_at' => now(),
        ]);
    }

    /**
     * Format subscription response
     */
    private function formatSubscription(Subscription $subscription): array
    {
        $company = $subscription->company;
        $package = $subscription->package;

        $companyName = $company?->name ?: ('Company #'.$subscription->company_id);
        $packageName = $package?->name ?: strtoupper((string) $subscription->plan_code ?: 'N/A');

        return [
            'id' => $subscription->id,
            'uuid' => $subscription->uuid,
            'companyId' => $subscription->company_id,
            'companyName' => $companyName,
            'company' => [
                'id' => $company?->id,
                'code' => $company?->code,
                'name' => $companyName,
            ],
            'packageId' => $subscription->package_uuid,
            'packageName' => $packageName,
            'package' => [
                'id' => $package?->uuid,
                'code' => $package?->code,
                'name' => $packageName,
                'monthlyPrice' => (float) ($package?->monthly_price ?? 0),
                'yearlyPrice' => (float) ($package?->yearly_price ?? 0),
            ],
            'planCode' => $subscription->plan_code,
            'status' => $subscription->status,
            'startsAt' => $subscription->starts_at?->toIso8601String(),
            'endsAt' => $subscription->ends_at?->toIso8601String(),
            'startDate' => $subscription->starts_at?->toDateString(),
            'endDate' => $subscription->ends_at?->toDateString(),
            'trialEndsAt' => $subscription->trial_ends_at?->toIso8601String(),
            'autoRenew' => $subscription->auto_renew,
            'billingCycle' => $subscription->billing_cycle,
            'amount' => (float)$subscription->amount,
            'durationDays' => $subscription->getDurationDays(),
            'isActive' => $subscription->isActive(),
            'isInTrial' => $subscription->isInTrial(),
            'isExpired' => $subscription->isExpired(),
            'createdAt' => $subscription->created_at->toIso8601String(),
            'updatedAt' => $subscription->updated_at->toIso8601String(),
        ];
    }

    /**
     * Check if user is HCM admin
     */
    private function isHcmAdmin(Request $request): bool
    {
        $user = $request->user();

        return $user ? $user->isGlobalHcmAdmin() : false;
    }
}
