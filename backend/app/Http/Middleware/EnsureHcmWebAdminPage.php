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

        if (! $user instanceof User) {
            return redirect()->to(url('login'));
        }

        if (! $user->isHcmAdmin()) {
            return redirect()->to(url('employee-dashboard'));
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
}
