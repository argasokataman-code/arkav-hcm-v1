<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomDomain;
use App\Models\DomainVerificationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomDomainController extends Controller
{
    /**
     * GET /v1/saas/domains
     * List custom domains with filtering
     */
    public function index(Request $request): JsonResponse
    {
        $query = CustomDomain::with(['company', 'verificationLogs']);

        // Filters
        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }
        if ($request->has('company_id')) {
            $query->where('company_id', $request->get('company_id'));
        }
        if ($request->has('domain')) {
            $query->where('domain', 'like', '%' . $request->get('domain') . '%');
        }

        $domains = $query->latest()->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $domains->map(fn ($d) => $this->formatDomain($d))->all(),
            'pagination' => [
                'total' => $domains->total(),
                'per_page' => $domains->perPage(),
                'current_page' => $domains->currentPage(),
                'last_page' => $domains->lastPage(),
            ],
        ]);
    }

    /**
     * GET /v1/saas/domains/{domain}
     * Get domain details including verification logs
     */
    public function show(CustomDomain $domain): JsonResponse
    {
        $domain->load('company', 'verificationLogs');

        return response()->json([
            'success' => true,
            'data' => $this->formatDomain($domain),
        ]);
    }

    /**
     * POST /v1/saas/domains
     * Create new custom domain (admin only)
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
            'domain' => 'required|string|unique:custom_domains,domain',
            'verification_method' => 'required|in:dns,file',
            'active_from' => 'nullable|date',
            'active_until' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        // Generate verification token
        $validated['verification_token'] = CustomDomain::generateVerificationToken();
        $validated['status'] = 'pending';

        $domain = CustomDomain::create($validated);
        $domain->load('company', 'verificationLogs');

        return response()->json([
            'success' => true,
            'data' => $this->formatDomain($domain),
        ], 201);
    }

    /**
     * PUT /v1/saas/domains/{domain}
     * Update domain configuration (admin only)
     */
    public function update(Request $request, CustomDomain $domain): JsonResponse
    {
        if (!$this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $validated = $request->validate([
            'active_from' => 'nullable|date',
            'active_until' => 'nullable|date',
            'status' => 'sometimes|in:pending,verified,failed,inactive',
            'notes' => 'nullable|string',
        ]);

        $domain->update($validated);
        $domain->load('company', 'verificationLogs');

        return response()->json([
            'success' => true,
            'data' => $this->formatDomain($domain),
        ]);
    }

    /**
     * DELETE /v1/saas/domains/{domain}
     * Delete domain (admin only)
     */
    public function destroy(Request $request, CustomDomain $domain): JsonResponse
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
            'message' => 'Domain deleted successfully',
        ]);
    }

    /**
     * POST /v1/saas/domains/{domain}/verify
     * Verify domain ownership (admin only)
     */
    public function verify(Request $request, CustomDomain $domain): JsonResponse
    {
        if (!$this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        // Simulate DNS verification (in production, would use actual DNS lookup)
        $verified = $this->performVerification($domain);

        // Log verification attempt
        DomainVerificationLog::create([
            'domain_id' => $domain->id,
            'status' => $verified ? 'verified' : 'failed',
            'verification_method' => $domain->verification_method,
            'details' => $verified ? 'DNS record found' : 'DNS record not found',
            'attempted_at' => now(),
        ]);

        // Update domain status
        if ($verified) {
            $domain->update([
                'status' => 'verified',
                'verified_at' => now(),
                'verification_response' => 'Domain ownership verified',
            ]);
        } else {
            $domain->increment('verification_attempts');
            $domain->update([
                'status' => $domain->verification_attempts >= 5 ? 'failed' : 'pending',
                'verification_failed_at' => now(),
                'last_verification_attempt_at' => now(),
                'verification_response' => 'Verification failed: DNS record not found',
            ]);
        }

        $domain->refresh();
        $domain->load('company', 'verificationLogs');

        return response()->json([
            'success' => $verified,
            'data' => $this->formatDomain($domain),
            'message' => $verified ? 'Domain verified successfully' : 'Domain verification failed',
        ]);
    }

    /**
     * Format domain response
     */
    private function formatDomain(CustomDomain $d): array
    {
        return [
            'id' => $d->id,
            'companyId' => $d->company_id,
            'company' => [
                'id' => $d->company->id,
                'code' => $d->company->code,
                'name' => $d->company->name,
            ],
            'domain' => $d->domain,
            'status' => $d->status,
            'verificationToken' => $d->verification_token,
            'isActive' => $d->isActive(),
            'isPending' => $d->isPending(),
            'hasFailed' => $d->hasFailed(),
            'verifiedAt' => $d->verified_at?->toIso8601String(),
            'verificationFailedAt' => $d->verification_failed_at?->toIso8601String(),
            'verificationMethod' => $d->verification_method,
            'verificationRecord' => $d->getVerificationRecord(),
            'verificationAttempts' => $d->verification_attempts,
            'lastVerificationAttemptAt' => $d->last_verification_attempt_at?->toIso8601String(),
            'activeFrom' => $d->active_from?->toDateString(),
            'activeUntil' => $d->active_until?->toDateString(),
            'notes' => $d->notes,
            'verificationLogs' => $d->verificationLogs->map(function ($log) {
                return [
                    'id' => $log->id,
                    'status' => $log->status,
                    'verificationMethod' => $log->verification_method,
                    'details' => $log->details,
                    'attemptedAt' => $log->attempted_at->toIso8601String(),
                ];
            }),
            'createdAt' => $d->created_at->toIso8601String(),
            'updatedAt' => $d->updated_at->toIso8601String(),
        ];
    }

    /**
     * Perform domain verification
     * In production, this would check actual DNS records
     */
    private function performVerification(CustomDomain $domain): bool
    {
        // Simulate: 70% success rate for demo purposes
        // In production: perform actual DNS lookup
        return rand(1, 100) <= 70;
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
