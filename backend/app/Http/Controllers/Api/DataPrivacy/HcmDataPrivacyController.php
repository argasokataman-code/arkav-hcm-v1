<?php

namespace App\Http\Controllers\Api\DataPrivacy;

use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessApprovedErasure;
use App\Mail\ConsentWithdrawalConfirmationMail;
use App\Models\CookieConsent;
use App\Models\EmployeeAiConsent;
use App\Models\EmployeeBiometricConsent;
use App\Models\EmployeeProfile;
use App\Models\ErasureRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
            'action' => ['required', Rule::in(['approve', 'reject'])],
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
            'status' => $newStatus,
            'reviewed_by_uuid' => (string) ($actor?->uuid ?? ''),
            'reviewed_at' => now(),
            'admin_notes' => $validated['admin_notes'] ?? null,
        ]);

        if ($newStatus === 'approved') {
            ProcessApprovedErasure::dispatch($erasureRequest->id)->afterCommit();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'uuid' => $erasureRequest->uuid,
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
            'gps_consent' => ['required', 'boolean'],
        ]);

        $profile = EmployeeProfile::query()
            ->where('user_id', $user->id)
            ->where('company_id', $companyId)
            ->firstOrFail();

        // Persist consent in a transaction and set timestamps defensively.
        $consent = null;
        DB::transaction(function () use ($profile, $companyId, $validated, $request, &$consent) {
            $attrs = [
                'selfie_consent' => (bool) $validated['selfie_consent'],
                'gps_consent' => (bool) $validated['gps_consent'],
                'consent_ip' => $request->ip(),
            ];

            if ($attrs['selfie_consent']) {
                $attrs['consent_given_at'] = now();
                $attrs['consent_withdrawn_at'] = null;
            } else {
                // Defensive: if storing false, mark withdrawn time.
                $attrs['consent_given_at'] = null;
                $attrs['consent_withdrawn_at'] = now();
            }

            $consent = EmployeeBiometricConsent::query()->updateOrCreate(
                [
                    'employee_uuid' => (string) $profile->uuid,
                    'company_id' => $companyId,
                ],
                $attrs
            );
        });

        // Log for audit/debug — safe, non-sensitive fields only.
        Log::info('Biometric consent saved', [
            'user_id' => $user->id ?? null,
            'employee_uuid' => $profile->uuid ?? null,
            'company_id' => $companyId,
            'selfie_consent' => $consent->selfie_consent ?? null,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'selfieConsent' => $consent->selfie_consent,
                'gpsConsent' => $consent->gps_consent,
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

        $profile = EmployeeProfile::query()
            ->where('user_id', $user->id)
            ->where('company_id', $companyId)
            ->firstOrFail();

        EmployeeBiometricConsent::query()
            ->where('employee_uuid', (string) $profile->uuid)
            ->where('company_id', $companyId)
            ->update([
                'selfie_consent' => false,
                'gps_consent' => false,
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

    // -------------------------------------------------------------------------
    // M8: Session re-verification for sensitive operations
    // -------------------------------------------------------------------------

    public function sessionCheck(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('UNAUTHENTICATED', 'Authentication required.', 401);
        }

        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (! \Illuminate\Support\Facades\Hash::check($validated['password'], $user->password)) {
            return $this->errorResponse('INVALID_CREDENTIALS', 'Password tidak sesuai.', 422);
        }

        $user->forceFill(['last_sensitive_verified_at' => now()])->save();

        return response()->json([
            'success' => true,
            'data' => [
                'verified' => true,
                'verifiedAt' => $user->last_sensitive_verified_at->toIso8601String(),
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // L2: "Data Saya" Portal (UU PDP Pasal 8 + 13 — hak akses & portabilitas)
    // -------------------------------------------------------------------------

    public function myData(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('UNAUTHENTICATED', 'Authentication required.', 401);
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->errorResponse('TENANT_CONTEXT_REQUIRED', 'Active company context is required.', 422);
        }

        $profile = EmployeeProfile::query()
            ->where('user_id', $user->id)
            ->where('company_id', $companyId)
            ->first();

        $biometricConsent = null;
        if ($profile) {
            $biometricConsent = EmployeeBiometricConsent::query()
                ->where('employee_uuid', (string) $profile->uuid)
                ->where('company_id', $companyId)
                ->first();
        }

        $aiConsent = null;
        if ($profile) {
            $aiConsent = EmployeeAiConsent::getActiveForEmployee((string) $profile->uuid);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'identity' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'uuid' => (string) $user->uuid,
                    'emailVerifiedAt' => $user->email_verified_at?->toIso8601String(),
                    'createdAt' => $user->created_at?->toIso8601String(),
                ],
                'profile' => $profile ? [
                    'uuid' => (string) $profile->uuid,
                    'nik' => $profile->nik,
                    'phone' => $profile->phone,
                    'address' => $profile->address,
                    'place_of_birth' => $profile->place_of_birth,
                    'date_of_birth' => $profile->date_of_birth,
                    'gender' => $profile->gender,
                    'marital_status' => $profile->marital_status,
                    'religion' => $profile->religion,
                    'nationality' => $profile->nationality,
                    'bank_name' => $profile->bank_name,
                    'bank_account_no' => $profile->bank_account_no,
                    'base_salary' => $profile->base_salary,
                    'fixed_allowance' => $profile->fixed_allowance,
                    'hire_date' => $profile->hire_date,
                ] : null,
                'consent' => [
                    'biometric' => $biometricConsent ? [
                        'selfieConsent' => $biometricConsent->selfie_consent,
                        'gpsConsent' => $biometricConsent->gps_consent,
                        'photoConsent' => $biometricConsent->photo_consent,
                        'consentGivenAt' => $biometricConsent->consent_given_at?->toIso8601String(),
                    ] : null,
                    'ai_chat' => $aiConsent ? [
                        'hasConsent' => true,
                        'consentGivenAt' => $aiConsent->consent_given_at?->toIso8601String(),
                    ] : null,
                ],
            ],
        ]);
    }

    public function exportMyData(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('UNAUTHENTICATED', 'Authentication required.', 401);
        }

        $companyId = $this->activeCompanyId($request);

        // Re-use myData logic
        $myDataResponse = $this->myData($request);
        $myData = json_decode($myDataResponse->getContent(), true);

        return response()->json([
            'success' => true,
            'data' => [
                'format' => 'json',
                'exportedAt' => now()->toIso8601String(),
                'payload' => $myData['data'] ?? null,
            ],
        ]);
    }

    /**
     * GET: /v1/hcm/data-privacy/me/biometric-consent-status
     * Return current employee biometric consent status for the active company.
     */
    public function checkBiometricConsentStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('UNAUTHENTICATED', 'Authentication required.', 401);
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->errorResponse('TENANT_CONTEXT_REQUIRED', 'Active company context is required.', 422);
        }

        $profile = EmployeeProfile::query()
            ->where('user_id', $user->id)
            ->where('company_id', $companyId)
            ->first();

        if (! $profile) {
            return response()->json(['success' => true, 'data' => ['biometric' => null]]);
        }

        $consent = EmployeeBiometricConsent::query()
            ->where('employee_uuid', $profile->uuid)
            ->where('company_id', $companyId)
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'biometric' => $consent ? [
                    'selfieConsent' => $consent->selfie_consent,
                    'gpsConsent' => $consent->gps_consent,
                    'photoConsent' => $consent->photo_consent,
                    'consentGivenAt' => $consent->consent_given_at,
                    'consentWithdrawnAt' => $consent->consent_withdrawn_at,
                ] : null,
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // M6: Photo consent (profile photo = biometric data, UU PDP Pasal 4 ayat 2)
    // -------------------------------------------------------------------------

    public function grantPhotoConsent(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('UNAUTHENTICATED', 'Authentication required.', 401);
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->errorResponse('TENANT_CONTEXT_REQUIRED', 'Active company context is required.', 422);
        }

        $profile = EmployeeProfile::query()
            ->where('user_id', $user->id)
            ->where('company_id', $companyId)
            ->firstOrFail();

        $consent = EmployeeBiometricConsent::query()->updateOrCreate(
            [
                'employee_uuid' => (string) $profile->uuid,
                'company_id' => $companyId,
            ],
            [
                'photo_consent' => true,
                'consent_given_at' => now(),
                'consent_ip' => $request->ip(),
            ]
        );

        return response()->json([
            'success' => true,
            'data' => [
                'photoConsent' => $consent->photo_consent,
                'consentGivenAt' => $consent->consent_given_at?->toIso8601String(),
            ],
        ]);
    }

    public function withdrawPhotoConsent(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('UNAUTHENTICATED', 'Authentication required.', 401);
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->errorResponse('TENANT_CONTEXT_REQUIRED', 'Active company context is required.', 422);
        }

        $profile = EmployeeProfile::query()
            ->where('user_id', $user->id)
            ->where('company_id', $companyId)
            ->firstOrFail();

        EmployeeBiometricConsent::query()
            ->where('employee_uuid', (string) $profile->uuid)
            ->where('company_id', $companyId)
            ->update([
                'photo_consent' => false,
                'consent_withdrawn_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'data' => ['withdrawn' => true],
        ]);
    }

    // -------------------------------------------------------------------------
    // H7: Cookie consent management
    // -------------------------------------------------------------------------

    public function saveCookieConsent(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('UNAUTHENTICATED', 'Authentication required.', 401);
        }

        $companyId = $this->activeCompanyId($request);

        $validated = $request->validate([
            'essential' => ['nullable', 'boolean'],
            'analytics' => ['nullable', 'boolean'],
            'marketing' => ['nullable', 'boolean'],
        ]);

        // Essential cookies are always required (cannot be rejected)
        $consent = CookieConsent::query()->updateOrCreate(
            [
                'user_uuid' => (string) $user->uuid,
                'company_id' => $companyId,
            ],
            [
                'essential' => true, // forced
                'analytics' => (bool) ($validated['analytics'] ?? false),
                'marketing' => (bool) ($validated['marketing'] ?? false),
                'consent_ip' => $request->ip(),
                'consented_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'data' => [
                'essential' => $consent->essential,
                'analytics' => $consent->analytics,
                'marketing' => $consent->marketing,
                'consentedAt' => $consent->consented_at?->toIso8601String(),
            ],
        ]);
    }

    public function getCookieConsent(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('UNAUTHENTICATED', 'Authentication required.', 401);
        }

        $companyId = $this->activeCompanyId($request);

        $consent = CookieConsent::query()
            ->where('user_uuid', (string) $user->uuid)
            ->where('company_id', $companyId)
            ->first();

        return response()->json([
            'success' => true,
            'data' => $consent ? [
                'essential' => $consent->essential,
                'analytics' => $consent->analytics,
                'marketing' => $consent->marketing,
                'consentedAt' => $consent->consented_at?->toIso8601String(),
            ] : null,
        ]);
    }
}
