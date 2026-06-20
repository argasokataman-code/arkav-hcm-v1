<?php

namespace App\Http\Controllers\Api\TaxGovernance\Concerns;

use App\Events\TaxGovernancePolicyTransitioned;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\EmployeeProfile;
use App\Models\EmployeeTaxProfile;
use App\Models\HcmBillingTaxPolicy;
use App\Models\HcmSalaryComponent;
use App\Models\HcmTaxGovernanceBreakGlassRequest;
use App\Models\HcmTaxGovernancePolicy;
use App\Models\HcmTaxGovernancePolicyEvent;
use App\Models\HcmTaxGovernanceProjection;
use App\Models\HcmTaxGovernanceAnomaly;
use App\Modelsser;
use App\Services\BillingTaxCalculationService;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;
use HandlesPlatformTaxGovernance;

trait HandlesTaxSharedUtilities
{
private function normalizeNpwp(string $value): string
    {
        return preg_replace('/[^0-9]/', '', trim($value)) ?? '';
    }

    private function isValidNpwpFormat(string $normalizedNpwp): bool
    {
        return preg_match('/^[0-9]{15,16}$/', $normalizedNpwp) === 1;
    }

    private function ensureTenantOwnerOrGlobalAdmin(Request $request): ?JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Authentication required.',
                ],
            ], 401);
        }

        if ($user->isGlobalHcmAdmin()) {
            return null;
        }

        $activeCompanyRole = strtolower(trim((string) $request->attributes->get('activeCompanyRole', '')));
        if ($activeCompanyRole === 'owner') {
            return null;
        }

        return $this->errorResponse('AUTH_FORBIDDEN', 'Only tenant owner can manage employee tax policy at this stage.', 403);
    }
}
