<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    /**
     * GET /v1/company/active
     * Fetch the active company (from tenant context) with full membership and subscription details.
     */
    public function active(Request $request): JsonResponse
    {
        /** @var Company|null $activeCompany */
        $activeCompany = $request->attributes->get('activeCompany');
        $activeUser = $request->user();

        if (!$activeCompany || !$activeUser) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TENANT_REQUIRED',
                    'message' => 'No active company in request context.',
                    'traceId' => $request->attributes->get('traceId'),
                ],
            ], 403);
        }

        // Load full relationships for enriched response
        $activeCompany->load('owner:id,name,email', 'users', 'subscriptions');

        // Get current user's membership info (role, joinedAt)
        $userMembership = $activeCompany->users()
            ->where('user_id', $activeUser->id)
            ->first();

        $subscriptionStatus = $activeCompany->subscriptions()
            ->where('status', 'active')
            ->orWhere('status', 'trial')
            ->latest('starts_at')
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $activeCompany->id,
                'code' => $activeCompany->code,
                'name' => $activeCompany->name,
                'legalName' => $activeCompany->legal_name,
                'status' => $activeCompany->status,
                'timezone' => $activeCompany->timezone,
                'currency' => $activeCompany->currency,
                'countryCode' => $activeCompany->country_code,
                'owner' => $activeCompany->owner ? [
                    'id' => $activeCompany->owner->id,
                    'name' => $activeCompany->owner->name,
                    'email' => $activeCompany->owner->email,
                ] : null,
                'memberCount' => $activeCompany->users->count(),
                'currentUserRole' => $userMembership?->role ?? null,
                'currentUserJoinedAt' => $userMembership?->joined_at ? $userMembership->joined_at->toIso8601String() : null,
                'subscription' => $subscriptionStatus ? [
                    'id' => $subscriptionStatus->id,
                    'planCode' => $subscriptionStatus->plan_code,
                    'status' => $subscriptionStatus->status,
                    'startsAt' => $subscriptionStatus->starts_at?->toIso8601String(),
                    'endsAt' => $subscriptionStatus->ends_at?->toIso8601String(),
                    'trialEndsAt' => $subscriptionStatus->trial_ends_at?->toIso8601String(),
                    'autoRenew' => $subscriptionStatus->auto_renew,
                ] : null,
                'createdAt' => $activeCompany->created_at->toIso8601String(),
                'updatedAt' => $activeCompany->updated_at->toIso8601String(),
            ],
        ]);
    }
}
