<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\ArcavAccessTokenResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Halaman HCM admin-only (Blade): non-admin diarahkan ke dashboard karyawan.
 * Harus dipasang setelah {@see EnsureHcmWebPagesAuthenticated} pada route GET/HEAD yang sama.
 */
class EnsureHcmWebAdminPage
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $this->resolveUser($request);
        
        \Log::debug('EnsureHcmWebAdminPage', [
            'user_resolved' => $user ? true : false,
            'user_email' => $user?->email ?? 'N/A',
            'isGlobalHcmAdmin' => $user?->isGlobalHcmAdmin() ?? false,
            'isHcmAdmin' => $user?->isHcmAdmin() ?? false,
            'path' => $request->path(),
        ]);

        if (! $user instanceof User) {
            \Log::warning('EnsureHcmWebAdminPage: User not resolved');
            return redirect()->to(url('login'));
        }

        if ($this->requiresGlobalHcmAdmin($request) && ! $user->isGlobalHcmAdmin()) {
            \Log::info('EnsureHcmWebAdminPage: Route requires global HCM admin', [
                'email' => $user->email,
                'path' => $request->path(),
            ]);

            return redirect()->to(url('employee-dashboard'));
        }

        // Global admin (super admin) bypasses all checks in developer mode
        if ($user->isGlobalHcmAdmin()) {
            \Log::info('EnsureHcmWebAdminPage: Global admin bypass', ['email' => $user->email]);
            return $next($request);
        }

        $activeCompanyId = (int) ($request->attributes->get('activeCompanyId') ?? 0);
        if ($activeCompanyId > 0) {
            if (! $user->isHcmAdminForCompany($activeCompanyId)) {
                    \Log::info('EnsureHcmWebAdminPage: Not HCM admin for company', ['activeCompanyId' => $activeCompanyId]);
                    return redirect()->to(url('employee-dashboard'))->with('error', 'Halaman ini khusus admin perusahaan. Anda sedang login sebagai pengguna employee/member.');
            }
            return $next($request);
        }

        if (! $user->isHcmAdmin()) {
            \Log::info('EnsureHcmWebAdminPage: Not HCM admin', ['email' => $user->email]);
            return redirect()->to(url('employee-dashboard'))->with('error', 'Halaman ini khusus admin perusahaan. Anda sedang login sebagai pengguna employee/member.');
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

    private function requiresGlobalHcmAdmin(Request $request): bool
    {
        return $request->routeIs('dashboard') || $request->routeIs('saas-dashboard');
    }
}
