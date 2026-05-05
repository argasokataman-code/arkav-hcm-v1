<?php

namespace App\Http\Controllers\Api;

use App\Models\EmployeeProfile;
use App\Models\EmployeeAiConsent;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HcmDataPrivacyAiController extends Controller
{
    /**
     * Grant AI Chat consent.
     * POST /v1/hcm/me/ai-consent
     *
     * UU PDP H3: Employees must explicitly consent before AI Chat data is sent to external service.
     */
    public function grantAiConsent(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'error' => 'UNAUTHORIZED',
            ], 401);
        }

        $employee = EmployeeProfile::query()
            ->where('user_uuid', $user->uuid)
            ->first();

        if (! $employee) {
            return response()->json([
                'success' => false,
                'error' => 'EMPLOYEE_PROFILE_NOT_FOUND',
            ], 404);
        }

        // Check if already consented
        $existing = EmployeeAiConsent::getActiveForEmployee($employee->uuid);
        if ($existing) {
            return response()->json([
                'success' => true,
                'data' => [
                    'message' => 'AI consent already granted',
                    'consent_given_at' => $existing->consent_given_at,
                ],
            ], 200);
        }

        // Create new consent record
        $consent = EmployeeAiConsent::create([
            'employee_uuid' => $employee->uuid,
            'user_uuid' => $user->uuid,
            'consent_given_at' => now(),
            'consent_ip_address' => $request->ip(),
            'consent_text' => $this->getConsentText(),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'message' => 'AI consent granted',
                'consent_given_at' => $consent->consent_given_at,
            ],
        ], 201);
    }

    /**
     * Withdraw AI Chat consent.
     * POST /v1/hcm/me/withdraw-ai-consent
     */
    public function withdrawAiConsent(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'error' => 'UNAUTHORIZED',
            ], 401);
        }

        $employee = EmployeeProfile::query()
            ->where('user_uuid', $user->uuid)
            ->first();

        if (! $employee) {
            return response()->json([
                'success' => false,
                'error' => 'EMPLOYEE_PROFILE_NOT_FOUND',
            ], 404);
        }

        $consent = EmployeeAiConsent::getActiveForEmployee($employee->uuid);
        if (! $consent) {
            return response()->json([
                'success' => false,
                'error' => 'NO_ACTIVE_CONSENT',
            ], 404);
        }

        // Withdraw consent
        $consent->update(['withdrawn_at' => now()]);

        return response()->json([
            'success' => true,
            'data' => [
                'message' => 'AI consent withdrawn',
                'withdrawn_at' => $consent->withdrawn_at,
            ],
        ], 200);
    }

    /**
     * Check if user has AI consent.
     * GET /v1/hcm/me/ai-consent-status
     */
    public function checkAiConsentStatus(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'error' => 'UNAUTHORIZED',
            ], 401);
        }

        $employee = EmployeeProfile::query()
            ->where('user_uuid', $user->uuid)
            ->first();

        if (! $employee) {
            return response()->json([
                'success' => false,
                'error' => 'EMPLOYEE_PROFILE_NOT_FOUND',
            ], 404);
        }

        $consent = EmployeeAiConsent::getActiveForEmployee($employee->uuid);

        return response()->json([
            'success' => true,
            'data' => [
                'has_consent' => $consent !== null,
                'consent_given_at' => $consent?->consent_given_at,
                'withdrawn_at' => $consent?->withdrawn_at,
            ],
        ], 200);
    }

    private function getConsentText(): string
    {
        return 'Fitur AI Chat menggunakan layanan AI pihak ketiga. Data percakapan Anda, termasuk pertanyaan dan konteks karyawan, akan dikirimkan ke server AI eksternal untuk diproses. Data ini akan disimpan dalam log selama 1 tahun, kemudian dihapus otomatis sesuai kebijakan retensi.';
    }
}
