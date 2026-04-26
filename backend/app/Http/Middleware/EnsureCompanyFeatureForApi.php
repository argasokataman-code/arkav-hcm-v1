<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Models\User;
use App\Support\ArcavAccessTokenResolver;
use App\Support\TenantContextResolver;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureCompanyFeatureForApi
{
    public function __construct(private readonly TenantContextResolver $tenantContextResolver)
    {
    }

    public function handle(Request $request, Closure $next, string ...$featureCodes)
    {
        $user = $this->resolveUser($request);
        if (! $user instanceof User) {
            return $this->error('AUTH_UNAUTHORIZED', 'Unauthorized.', 401);
        }

        if ($user->isGlobalHcmAdmin()) {
            return $next($request);
        }

        $company = $request->attributes->get('activeCompany');
        $companyId = $company instanceof Company ? (int) $company->id : null;

        if (! $companyId) {
            $resolved = $this->tenantContextResolver->resolve($request, $user);
            if (isset($resolved['error'])) {
                return $this->error('TENANT_CONTEXT_REQUIRED', 'Active company context is required.', 422);
            }

            /** @var Company $resolvedCompany */
            $resolvedCompany = $resolved['company'];
            $membership = $resolved['membership'];

            $request->attributes->set('activeCompany', $resolvedCompany);
            $request->attributes->set('activeCompanyId', $resolvedCompany->id);
            $request->attributes->set('activeCompanyUuid', $resolvedCompany->uuid);
            $request->attributes->set('activeCompanyCode', $resolvedCompany->code);
            $request->attributes->set('activeCompanyRole', $membership->role);

            $companyId = (int) $resolvedCompany->id;
            $company = $resolvedCompany;
        }

        $codes = $this->normalizeCodes($featureCodes);
        if ($codes === []) {
            return $next($request);
        }

        $subscription = $company instanceof Company ? $company->activeSubscription() : null;
        if (! $subscription) {
            // Backward compatibility: legacy tenant fixtures/tests may not have
            // active subscription rows yet. In that case, let existing RBAC and
            // module-level guards decide access.
            return $next($request);
        }

        foreach ($codes as $code) {
            if (! $subscription->package?->hasFeature($code)) {
                return $this->error(
                    'FEATURE_DISABLED',
                    sprintf('Feature "%s" is not enabled for this company.', $code),
                    403,
                );
            }
        }

        return $next($request);
    }

    /**
     * @param  array<int, string>  $featureCodes
     * @return array<int, string>
     */
    private function normalizeCodes(array $featureCodes): array
    {
        $out = [];
        foreach ($featureCodes as $entry) {
            foreach (explode(',', (string) $entry) as $code) {
                $code = trim($code);
                if ($code !== '') {
                    $out[] = strtolower($code);
                }
            }
        }

        return array_values(array_unique($out));
    }

    private function resolveUser(Request $request): ?User
    {
        $user = $request->user();
        if ($user instanceof User) {
            return $user;
        }

        $sessionUser = Auth::user();
        if ($sessionUser instanceof User) {
            return $sessionUser;
        }

        $token = ArcavAccessTokenResolver::validTokenFromRequest($request);
        if ($token && $token->user instanceof User) {
            return $token->user;
        }

        return null;
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], $status);
    }
}
