<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DomainController extends Controller
{
    /**
     * GET /v1/saas/domains
     * List all domains (admin only)
     */
    public function index(Request $request): JsonResponse
    {
        if (!$this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $query = Domain::with('company');

        // Filter by status
        if ($request->has('status') && $request->get('status')) {
            $query->where('status', $request->get('status'));
        }

        // Filter by company_id
        if ($request->has('company_id') && $request->get('company_id')) {
            $query->where('company_id', $request->get('company_id'));
        }

        // Search by domain name
        if ($request->has('search') && $request->get('search')) {
            $query->where('domain_name', 'like', '%' . $request->get('search') . '%');
        }

        $domains = $query
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        $items = collect($domains->items())
            ->map(fn(Domain $domain) => [
                'id' => $domain->id,
                'domainName' => $domain->domain_name,
                'companyId' => $domain->company_id,
                'companyName' => $domain->company?->name,
                'verificationType' => $domain->verification_type,
                'status' => $domain->status,
                'verificationToken' => $domain->verification_token,
                'verificationData' => $domain->verification_data,
                'verifiedAt' => $domain->verified_at?->toIso8601String(),
                'notes' => $domain->notes,
                'createdAt' => $domain->created_at->toIso8601String(),
                'updatedAt' => $domain->updated_at->toIso8601String(),
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => $items,
            'pagination' => [
                'total' => $domains->total(),
                'per_page' => $domains->perPage(),
                'current_page' => $domains->currentPage(),
                'last_page' => $domains->lastPage(),
            ],
        ]);
    }

    /**
     * GET /v1/saas/domains/{id}
     * Get domain details
     */
    public function show(Request $request, Domain $domain): JsonResponse
    {
        if (!$this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $domain->load('company');

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $domain->id,
                'domainName' => $domain->domain_name,
                'companyId' => $domain->company_id,
                'companyName' => $domain->company?->name,
                'verificationType' => $domain->verification_type,
                'status' => $domain->status,
                'verificationToken' => $domain->verification_token,
                'verificationData' => $domain->verification_data,
                'verifiedAt' => $domain->verified_at?->toIso8601String(),
                'notes' => $domain->notes,
                'createdAt' => $domain->created_at->toIso8601String(),
                'updatedAt' => $domain->updated_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * POST /v1/saas/domains
     * Create new domain (admin only)
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
            'domain_name' => 'required|string|unique:domains|max:255',
            'company_id' => 'required|uuid|exists:companies,uuid',
            'verification_type' => 'required|in:dns,file',
            'notes' => 'nullable|string',
        ]);

        $companyId = Company::query()->where('uuid', $validated['company_id'])->value('id');

        // Generate verification token
        $validated['verification_token'] = \Illuminate\Support\Str::random(32);
        $validated['status'] = 'pending';
        $validated['company_id'] = $companyId;

        $domain = Domain::create($validated);
        $domain->load('company');

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $domain->id,
                'domainName' => $domain->domain_name,
                'companyId' => $domain->company_id,
                'companyName' => $domain->company?->name,
                'verificationType' => $domain->verification_type,
                'status' => $domain->status,
                'verificationToken' => $domain->verification_token,
                'notes' => $domain->notes,
                'createdAt' => $domain->created_at->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * PUT /v1/saas/domains/{id}
     * Update domain (admin only)
     */
    public function update(Request $request, Domain $domain): JsonResponse
    {
        if (!$this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $validated = $request->validate([
            'domain_name' => 'sometimes|string|unique:domains,domain_name,' . $domain->id . '|max:255',
            'company_id' => 'sometimes|uuid|exists:companies,uuid',
            'verification_type' => 'sometimes|in:dns,file',
            'notes' => 'nullable|string',
            'status' => 'sometimes|in:pending,verified,failed',
        ]);

        if (array_key_exists('company_id', $validated)) {
            $validated['company_id'] = Company::query()->where('uuid', $validated['company_id'])->value('id');
        }

        $domain->update($validated);
        $domain->load('company');

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $domain->id,
                'domainName' => $domain->domain_name,
                'companyId' => $domain->company_id,
                'companyName' => $domain->company?->name,
                'verificationType' => $domain->verification_type,
                'status' => $domain->status,
                'verificationToken' => $domain->verification_token,
                'notes' => $domain->notes,
                'updatedAt' => $domain->updated_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * DELETE /v1/saas/domains/{id}
     * Delete domain (admin only)
     */
    public function destroy(Request $request, Domain $domain): JsonResponse
    {
        if (!$this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $domain->delete();

        return response()->json([
            'success' => true,
            'message' => 'Domain deleted successfully.',
        ]);
    }

    /**
     * POST /v1/saas/domains/{id}/verify
     * Verify domain (admin only)
     */
    public function verify(Request $request, Domain $domain): JsonResponse
    {
        if (!$this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        // Simple verification: mark as verified if pending
        if ($domain->status === 'pending') {
            $domain->update([
                'status' => 'verified',
                'verified_at' => now(),
            ]);
        }

        $domain->load('company');

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $domain->id,
                'domainName' => $domain->domain_name,
                'status' => $domain->status,
                'verifiedAt' => $domain->verified_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * GET /v1/saas/domains/{id}/verification-details
     * Get verification instructions
     */
    public function verificationDetails(Request $request, Domain $domain): JsonResponse
    {
        if (!$this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        if ($domain->verification_type === 'dns') {
            $instructions = [
                'type' => 'DNS',
                'step1' => 'Go to your domain registrar DNS settings',
                'step2' => "Add TXT record: arcav-verification={$domain->verification_token}",
                'step3' => 'Wait for DNS propagation (up to 24 hours)',
                'step4' => 'Click Verify Now button below',
            ];
        } else {
            $instructions = [
                'type' => 'File Upload',
                'step1' => 'Create file: arcav-verification.txt',
                'step2' => "Content: {$domain->verification_token}",
                'step3' => "Upload to: {$domain->domain_name}/.well-known/",
                'step4' => 'Click Verify Now button below',
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'domainName' => $domain->domain_name,
                'verificationType' => $domain->verification_type,
                'instructions' => $instructions,
                'token' => $domain->verification_token,
            ],
        ]);
    }

    /**
     * Helper: Check if user is HCM Admin (from trait or request context)
     */
    private function isHcmAdmin(Request $request): bool
    {
        $user = $request->user();
        if (!$user) {
            return false;
        }

        return $user->isGlobalHcmAdmin();
    }
}
