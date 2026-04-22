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
                'uuid' => $activeCompany->uuid,
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

    /**
     * GET /v1/company
     * List all companies (for admin users; others only see their own).
     * Supports pagination, filtering by status.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $page = (int) $request->get('page', 1);
        $perPage = (int) $request->get('per_page', 10);
        $status = $request->get('status'); // null, 'active', 'inactive'

        // Build query: admin can see all, others only their joined companies
        $baseQuery = Company::query();

        if (! $this->isHcmAdmin($request)) {
            // Non-admin users see only companies they're members of
            $baseQuery->whereHas('users', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        $statsScope = clone $baseQuery;
        $totalCompanies = (clone $statsScope)->count();
        $activeCompanies = (clone $statsScope)->where('status', 'active')->count();
        $inactiveCompanies = (clone $statsScope)->where('status', 'inactive')->count();
        $locationCount = (clone $statsScope)->whereNotNull('country_code')->distinct('country_code')->count('country_code');

        $query = clone $baseQuery;

        // Apply status filter
        if ($status) {
            $query->where('status', $status);
        }

        $companies = $query
            ->with('owner:id,name,email', 'subscriptions')
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        $companyRows = collect($companies->items())->map(function (Company $company) {
            $latest = $company->subscriptions
                ->sortByDesc(function ($s) {
                    return $s->starts_at ?: $s->created_at;
                })
                ->first();

            return [
                'id' => $company->id,
                'uuid' => $company->uuid,
                'code' => $company->code,
                'name' => $company->name,
                'legal_name' => $company->legal_name,
                'status' => $company->status,
                'timezone' => $company->timezone,
                'currency' => $company->currency,
                'country_code' => $company->country_code,
                'created_at' => $company->created_at?->toIso8601String(),
                'owner' => $company->owner ? [
                    'id' => $company->owner->id,
                    'name' => $company->owner->name,
                    'email' => $company->owner->email,
                ] : null,
                'subscription' => $latest ? [
                    'id' => $latest->id,
                    'status' => $latest->status,
                    'planCode' => $latest->plan_code,
                    'startsAt' => $latest->starts_at?->toIso8601String(),
                    'endsAt' => $latest->ends_at?->toIso8601String(),
                    'trialEndsAt' => $latest->trial_ends_at?->toIso8601String(),
                ] : null,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'companies' => $companyRows,
                'pagination' => [
                    'total' => $companies->total(),
                    'per_page' => $companies->perPage(),
                    'page' => $companies->currentPage(),
                    'last_page' => $companies->lastPage(),
                ],
                'stats' => [
                    'totalCompanies' => $totalCompanies,
                    'activeCompanies' => $activeCompanies,
                    'inactiveCompanies' => $inactiveCompanies,
                    'locationCount' => $locationCount,
                ],
            ],
        ]);
    }

    /**
     * POST /v1/company
     * Create a new company (admin only).
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        // Only admins can create companies
        if (! $this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Only administrators can create companies.',
                ],
            ], 403);
        }

        $validated = $request->validate([
            'code' => ['required', 'string', Rule::unique('companies', 'code'), 'max:100'],
            'name' => 'required|string|max:255',
            'legal_name' => 'nullable|string|max:255',
            'status' => 'required|in:active,inactive',
            'timezone' => 'required|string|max:100',
            'currency' => 'required|string|max:10',
            'country_code' => 'required|string|max:10',
        ]);

        $validated['owner_user_id'] = $user->id;

        $company = Company::create($validated);
        $company->load('owner:id,name,email');

        return response()->json([
            'success' => true,
            'data' => $company,
        ], 201);
    }

    /**
     * PUT /v1/company/{id}
     * Update an existing company (admin only).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $company = Company::find($id);

        if (!$company) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Company not found.',
                ],
            ], 404);
        }

        // Only admin or owner can edit
        if (! $this->isHcmAdmin($request) && $company->owner_user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'You do not have permission to edit this company.',
                ],
            ], 403);
        }

        $validated = $request->validate([
            'code' => ['sometimes', 'string', Rule::unique('companies', 'code')->ignore($id), 'max:100'],
            'name' => 'sometimes|string|max:255',
            'legal_name' => 'nullable|string|max:255',
            'status' => 'sometimes|in:active,inactive',
            'timezone' => 'sometimes|string|max:100',
            'currency' => 'sometimes|string|max:10',
            'country_code' => 'sometimes|string|max:10',
        ]);

        $company->update($validated);
        $company->load('owner:id,name,email');

        return response()->json([
            'success' => true,
            'data' => $company,
        ]);
    }

    /**
     * DELETE /v1/company/{id}
     * Delete a company (admin only).
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $company = Company::find($id);

        if (!$company) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Company not found.',
                ],
            ], 404);
        }

        // Only admin can delete
        if (! $this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Only administrators can delete companies.',
                ],
            ], 403);
        }

        $company->delete();

        return response()->json([
            'success' => true,
            'message' => 'Company deleted successfully.',
        ]);
    }

    private function isHcmAdmin(Request $request): bool
    {
        $user = $request->user();

        return $user ? $user->isGlobalHcmAdmin() : false;
    }
}
