<?php

namespace App\Http\Controllers\Api\TaxGovernance\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
