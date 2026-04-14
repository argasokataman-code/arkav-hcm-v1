<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\ArcavAccessTokenResolver;
use App\Support\TenantContextResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureHcmWebPagesAuthenticated
{
    public function __construct(private readonly TenantContextResolver $tenantContextResolver)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->pathRequiresToken($request)) {
            return $next($request);
        }

        $token = ArcavAccessTokenResolver::validTokenFromRequest($request);
        if ($token && $token->user) {
            $request->setUserResolver(fn () => $token->user);
            $this->resolveTenantContext($request, $token->user);

            return $next($request);
        }

        // Legacy web login (custom-login / session guard) tanpa cookie API — tetap boleh render halaman.
        if (Auth::check()) {
            $sessionUser = Auth::user();
            if ($sessionUser instanceof User) {
                $request->setUserResolver(fn () => $sessionUser);
                $this->resolveTenantContext($request, $sessionUser);
            }

            return $next($request);
        }

        return redirect()
            ->guest(url('lock-screen'))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    private function pathRequiresToken(Request $request): bool
    {
        if ($request->method() !== 'GET' && $request->method() !== 'HEAD') {
            return false;
        }

        $path = trim($request->path(), '/');

        /** @var array<string, mixed> $cfg */
        $cfg = config('arcav_hcm_web_guard', []);

        return ! $this->isPublicWebPath($path, $cfg);
    }

    /**
     * @param  array<string, mixed>  $cfg
     */
    private function isPublicWebPath(string $path, array $cfg): bool
    {
        foreach ($cfg['public_paths'] ?? [] as $p) {
            $p = trim((string) $p, '/');
            if ($path === $p) {
                return true;
            }
        }

        foreach ($cfg['public_prefixes'] ?? [] as $prefix) {
            $prefix = trim((string) $prefix, '/');
            if ($prefix === '') {
                continue;
            }
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }

    private function resolveTenantContext(Request $request, User $user): void
    {
        $result = $this->tenantContextResolver->resolve($request, $user);
        if (isset($result['error'])) {
            return;
        }

        $company = $result['company'] ?? null;
        $membership = $result['membership'] ?? null;
        if (! $company || ! $membership) {
            return;
        }

        $request->attributes->set('activeCompany', $company);
        $request->attributes->set('activeCompanyId', $company->id);
        $request->attributes->set('activeCompanyCode', $company->code);
        $request->attributes->set('activeCompanyRole', $membership->role);
    }
}
