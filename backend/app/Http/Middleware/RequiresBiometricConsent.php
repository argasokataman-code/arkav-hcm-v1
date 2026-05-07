<?php

namespace App\Http\Middleware;

use App\Models\EmployeeBiometricConsent;
use App\Models\EmployeeProfile;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequiresBiometricConsent
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Authentication required.'],
            ], 401);
        }

        $companyId = (int) $request->header('X-Company-Id');
        if (! $companyId) {
            $companyId = (int) $request->attributes->get('activeCompanyId', 0);
        }
        if (! $companyId) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'TENANT_CONTEXT_REQUIRED', 'message' => 'Company context required.'],
            ], 422);
        }

        $profile = EmployeeProfile::query()
            ->where('user_id', $user->id)
            ->where('company_id', $companyId)
            ->first();

        // No employee profile — let the controller handle this case (it will return the appropriate error).
        if (! $profile) {
            return $next($request);
        }

        $consent = EmployeeBiometricConsent::query()
            ->where('employee_uuid', $profile->uuid)
            ->where('company_id', $companyId)
            ->first();

        if (! $consent || ! $consent->selfie_consent || $consent->consent_withdrawn_at !== null) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'BIOMETRIC_CONSENT_REQUIRED',
                    'message' => 'Persetujuan penggunaan data biometrik (foto selfie) diperlukan sebelum check-in. Harap berikan persetujuan terlebih dahulu.',
                ],
            ], 403);
        }

        return $next($request);
    }
}
