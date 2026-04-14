<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    /**
     * GET /v1/saas/subscriptions
     * List subscriptions (admin, with filtering)
     */
    public function index(Request $request): JsonResponse
    {
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

        $subscriptions = $query->latest('created_at')->paginate(15);

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
        if (!$this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $validated = $request->validate([
            'company_id' => 'required|integer|exists:companies,id',
            'package_id' => 'required|integer|exists:packages,id',
            'status' => 'required|in:active,trial,inactive,expired,cancelled',
            'starts_at' => 'required|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'trial_ends_at' => 'nullable|date',
            'auto_renew' => 'boolean',
            'billing_cycle' => 'required|in:monthly,yearly',
            'amount' => 'nullable|numeric|min:0',
        ]);

        // Get package to denormalize plan_code and calculate amount
        $package = Package::findOrFail($validated['package_id']);
        $validated['plan_code'] = $package->code;

        // Calculate amount if not provided
        if (!isset($validated['amount']) || $validated['amount'] === null) {
            if ($validated['billing_cycle'] === 'yearly') {
                $validated['amount'] = $package->yearly_price;
            } else {
                $validated['amount'] = $package->monthly_price;
            }
        }

        $subscription = Subscription::create($validated);
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
    public function show(Subscription $subscription): JsonResponse
    {
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
        if (!$this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $validated = $request->validate([
            'package_id' => 'sometimes|integer|exists:packages,id',
            'status' => 'sometimes|in:active,trial,inactive,expired,cancelled',
            'starts_at' => 'sometimes|date',
            'ends_at' => 'nullable|date',
            'trial_ends_at' => 'nullable|date',
            'auto_renew' => 'sometimes|boolean',
            'billing_cycle' => 'sometimes|in:monthly,yearly',
            'amount' => 'nullable|numeric|min:0',
        ]);

        // If package changed, update plan_code and possibly amount
        if (isset($validated['package_id'])) {
            $package = Package::findOrFail($validated['package_id']);
            $validated['plan_code'] = $package->code;
        }

        $subscription->update($validated);
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
        if (!$this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $subscription->delete();

        return response()->json([
            'success' => true,
            'message' => 'Subscription cancelled successfully.',
        ]);
    }

    /**
     * POST /v1/saas/subscriptions/{id}/renew
     * Renew subscription (admin)
     */
    public function renew(Request $request, Subscription $subscription): JsonResponse
    {
        if (!$this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $validated = $request->validate([
            'ends_at' => 'required|date|after:now',
        ]);

        $subscription->update([
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => $validated['ends_at'],
            'trial_ends_at' => null,
        ]);

        $subscription->load('company', 'package');

        return response()->json([
            'success' => true,
            'data' => $this->formatSubscription($subscription),
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
            'companyId' => $subscription->company_id,
            'companyName' => $companyName,
            'company' => [
                'id' => $company?->id,
                'code' => $company?->code,
                'name' => $companyName,
            ],
            'packageId' => $subscription->package_id,
            'packageName' => $packageName,
            'package' => [
                'id' => $package?->id,
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
        return $user ? $user->isHcmAdmin() : false;
    }
}
