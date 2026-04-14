<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Models\User;
use App\Services\AssetService;
use App\Support\ArcavAccessTokenResolver;
use App\Support\TenantContextResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAssetManagementWebAccess
{
    public function __construct(
        private readonly AssetService $assetService,
        private readonly TenantContextResolver $tenantContextResolver,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $this->resolveUser($request);
        if (! $user instanceof User) {
            return redirect()->to(url('login'));
        }

        if ($this->isQaSuperAdmin($user)) {
            return $next($request);
        }

        $company = $request->attributes->get('activeCompany');
        $companyId = $company instanceof Company ? (int) $company->id : null;

        if (! $companyId) {
            $resolved = $this->tenantContextResolver->resolve($request, $user);
            if (isset($resolved['error'])) {
                return redirect()->to(url('employee-dashboard'));
            }

            /** @var Company $resolvedCompany */
            $resolvedCompany = $resolved['company'];
            $membership = $resolved['membership'];

            $request->attributes->set('activeCompany', $resolvedCompany);
            $request->attributes->set('activeCompanyId', $resolvedCompany->id);
            $request->attributes->set('activeCompanyCode', $resolvedCompany->code);
            $request->attributes->set('activeCompanyRole', $membership->role);
            $companyId = (int) $resolvedCompany->id;
        }

        if (! $this->assetService->companyHasFeature($companyId, AssetService::FEATURE_ASSET_MANAGEMENT)) {
            return redirect()
                ->to(url('employee-dashboard'))
                ->with('error', 'Asset Management is not enabled for this company.');
        }

        return $next($request);
    }

    private function resolveUser(Request $request): ?User
    {
        $u = $request->user();
        if ($u instanceof User) {
            return $u;
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

    private function isQaSuperAdmin(User $user): bool
    {
        $email = strtolower(trim((string) ($user->email ?? '')));
        $qaAdminEmail = strtolower(trim((string) config('hcm.admin_email', 'qa.login@example.com')));

        return $email === $qaAdminEmail || (bool) ($user->is_super_admin ?? false);
    }
}
