<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Http\Controllers\Controller;
use App\Mail\ConsentWithdrawalConfirmationMail;
use App\Models\ErasureRequest;
use App\Models\EmployeeAiConsent;
use App\Models\EmployeeBiometricConsent;
use App\Models\EmployeeProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class HcmDataPrivacyController extends Controller
{
    use ChecksPermissions;

    private function errorResponse(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['success' => false, 'error' => ['code' => $code, 'message' => $message]], $status);
    }
    // -------------------------------------------------------------------------
    // Employee: request own data erasure (Pasal 43-44 UU PDP)
    // -------------------------------------------------------------------------

    public function requestErasure(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('UNAUTHENTICATED', 'Authentication required.', 401);
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->errorResponse('TENANT_CONTEXT_REQUIRED', 'Active company context is required.', 422);
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        // Prevent duplicate pending requests
        $existingPending = ErasureRequest::query()
            ->where('subject_uuid', $user->uuid)
            ->where('company_id', $companyId)
            ->where('status', 'pending')
            ->exists();

        if ($existingPending) {
            return $this->errorResponse(
                'ERASURE_REQUEST_DUPLICATE',
                'You already have a pending erasure request. Please wait for it to be processed.',
                422
            );
        }

        $erasureRequest = ErasureRequest::query()->create([
            'uuid' => Str::uuid(),
            'subject_uuid' => (string) $user->uuid,
            'company_id' => $companyId,
            'status' => 'pending',
            'reason' => $validated['reason'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'uuid' => $erasureRequest->uuid,
                'status' => $erasureRequest->status,
                'createdAt' => $erasureRequest->created_at,
            ],
        ], 201);
    }

    public function listMyErasureRequests(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('UNAUTHENTICATED', 'Authentication required.', 401);
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->errorResponse('TENANT_CONTEXT_REQUIRED', 'Active company context is required.', 422);
        }

        $requests = ErasureRequest::query()
            ->where('subject_uuid', $user->uuid)
            ->where('company_id', $companyId)
            ->orderByDesc('created_at')
            ->get(['uuid', 'status', 'reason', 'admin_notes', 'reviewed_at', 'completed_at', 'created_at']);

        return response()->json([
            'success' => true,
            'data' => $requests,
        ]);
    }

    // -------------------------------------------------------------------------
    // Admin: list and process erasure requests
    // -------------------------------------------------------------------------

    public function listErasureRequests(Request $request): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'user_management.manage')) {
            return $response;
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->errorResponse('TENANT_CONTEXT_REQUIRED', 'Active company context is required.', 422);
        }

        $requests = ErasureRequest::query()
            ->where('company_id', $companyId)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $requests,
        ]);
    }

    public function processErasure(Request $request, string $uuid): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'user_management.manage')) {
            return $response;
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->errorResponse('TENANT_CONTEXT_REQUIRED', 'Active company context is required.', 422);
        }

        $validated = $request->validate([
            'action'      => ['required', Rule::in(['approve', 'reject'])],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $erasureRequest = ErasureRequest::query()
            ->where('uuid', $uuid)
            ->where('company_id', $companyId)
            ->where('status', 'pending')
            ->firstOrFail();

        $actor = $request->user();
        $newStatus = $validated['action'] === 'approve' ? 'approved' : 'rejected';

        $erasureRequest->update([
            'status'           => $newStatus,
            'reviewed_by_uuid' => (string) ($actor?->uuid ?? ''),
            'reviewed_at'      => now(),
            'admin_notes'      => $validated['admin_notes'] ?? null,
        ]);

        if ($newStatus === 'approved') {
            \App\Jobs\ProcessApprovedErasure::dispatch($erasureRequest->id)->afterCommit();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'uuid'   => $erasureRequest->uuid,
                'status' => $erasureRequest->status,
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // Biometric consent management
    // -------------------------------------------------------------------------

    public function storeBiometricConsent(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('UNAUTHENTICATED', 'Authentication required.', 401);
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->errorResponse('TENANT_CONTEXT_REQUIRED', 'Active company context is required.', 422);
        }

        $validated = $request->validate([
            'selfie_consent' => ['required', 'boolean'],
            'gps_consent'    => ['required', 'boolean'],
        ]);

        $profile = \App\Models\EmployeeProfile::query()
            ->where('user_id', $user->id)
            ->where('company_id', $companyId)
            ->firstOrFail();

        $consent = \App\Models\EmployeeBiometricConsent::query()->updateOrCreate(
            [
                'employee_uuid' => (string) $profile->uuid,
                'company_id'    => $companyId,
            ],
            [
                'selfie_consent'       => $validated['selfie_consent'],
                'gps_consent'          => $validated['gps_consent'],
                'consent_given_at'     => now(),
                'consent_withdrawn_at' => null,
                'consent_ip'           => $request->ip(),
            ]
        );

        return response()->json([
            'success' => true,
            'data' => [
                'selfieConsent' => $consent->selfie_consent,
                'gpsConsent'    => $consent->gps_consent,
                'consentGivenAt' => $consent->consent_given_at,
            ],
        ]);
    }

    public function withdrawBiometricConsent(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('UNAUTHENTICATED', 'Authentication required.', 401);
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->errorResponse('TENANT_CONTEXT_REQUIRED', 'Active company context is required.', 422);
        }

        $profile = \App\Models\EmployeeProfile::query()
            ->where('user_id', $user->id)
            ->where('company_id', $companyId)
            ->firstOrFail();

        \App\Models\EmployeeBiometricConsent::query()
            ->where('employee_uuid', (string) $profile->uuid)
            ->where('company_id', $companyId)
            ->update([
                'selfie_consent'       => false,
                'gps_consent'          => false,
                'consent_withdrawn_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'data' => ['withdrawn' => true],
        ]);
    }

    public function withdrawConsent(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('UNAUTHENTICATED', 'Authentication required.', 401);
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->errorResponse('TENANT_CONTEXT_REQUIRED', 'Active company context is required.', 422);
        }

        $validated = $request->validate([
            'scope' => ['required', Rule::in(['ai_chat', 'biometric', 'all'])],
        ]);

        $profile = EmployeeProfile::query()
            ->where('user_id', $user->id)
            ->where('company_id', $companyId)
            ->firstOrFail();

        $scope = (string) $validated['scope'];
        $withdrawn = [
            'ai_chat' => false,
            'biometric' => false,
        ];

        if ($scope === 'ai_chat' || $scope === 'all') {
            EmployeeAiConsent::query()
                ->where('employee_uuid', (string) $profile->uuid)
                ->whereNull('withdrawn_at')
                ->update(['withdrawn_at' => now()]);

            $withdrawn['ai_chat'] = true;
        }

        if ($scope === 'biometric' || $scope === 'all') {
            EmployeeBiometricConsent::query()
                ->where('employee_uuid', (string) $profile->uuid)
                ->where('company_id', $companyId)
                ->update([
                    'selfie_consent' => false,
                    'gps_consent' => false,
                    'consent_withdrawn_at' => now(),
                ]);

            $withdrawn['biometric'] = true;
        }

        if (is_string($user->email) && trim($user->email) !== '') {
            Mail::to($user->email)->queue(new ConsentWithdrawalConfirmationMail(
                $user,
                $scope,
                $withdrawn,
            ));
        }

        return response()->json([
            'success' => true,
            'data' => [
                'scope' => $scope,
                'withdrawn' => $withdrawn,
                'withdrawnAt' => now(),
            ],
        ]);
    }
}
