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

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        // Filter by company_id
        if ($request->has('company_id')) {
            $query->where('company_id', $request->get('company_id'));
        }

        // Filter by plan_code
        if ($request->has('plan_code')) {
            $query->where('plan_code', $request->get('plan_code'));
        }

        $subscriptions = $query->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $subscriptions->items(),
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
        return [
            'id' => $subscription->id,
            'companyId' => $subscription->company_id,
            'company' => [
                'id' => $subscription->company->id,
                'code' => $subscription->company->code,
                'name' => $subscription->company->name,
            ],
            'packageId' => $subscription->package_id,
            'package' => [
                'id' => $subscription->package->id,
                'code' => $subscription->package->code,
                'name' => $subscription->package->name,
                'monthlyPrice' => (float)$subscription->package->monthly_price,
                'yearlyPrice' => (float)$subscription->package->yearly_price,
            ],
            'planCode' => $subscription->plan_code,
            'status' => $subscription->status,
            'startsAt' => $subscription->starts_at?->toIso8601String(),
            'endsAt' => $subscription->ends_at?->toIso8601String(),
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
        if (!$user) return false;

        $adminEmail = config('hcm.admin_email', 'qa.login@example.com');
        if ($user->email === $adminEmail) return true;

        $adminKeywords = ['admin', 'hr', 'lead', 'supervisor', 'owner'];
        $designation = strtolower($user->designation ?? '');
        $team = strtolower($user->team ?? '');

        foreach ($adminKeywords as $keyword) {
            if (str_contains($designation, $keyword) || str_contains($team, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
