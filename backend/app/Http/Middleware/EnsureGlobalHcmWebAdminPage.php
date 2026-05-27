<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\ArcavAccessTokenResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Halaman SaaS global-admin-only (Blade): admin tenant biasa tetap ditolak.
 * Harus dipasang setelah {@see EnsureHcmWebPagesAuthenticated} pada route GET/HEAD yang sama.
 */
class EnsureGlobalHcmWebAdminPage
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $this->resolveUser($request);

        \Log::info('EnsureGlobalHcmWebAdminPage', [
            'path' => $request->path(),
            'user_found' => $user ? 'yes' : 'no',
            'user_email' => $user?->email ?? 'none',
            'is_global_admin' => $user?->isGlobalHcmAdmin() ? 'yes' : 'no'
        ]);

        if (! $user instanceof User) {
            return redirect()->to(url('login'));
        }

        if (! $user->isGlobalHcmAdmin()) {
            return redirect()->to(url('employee-dashboard'));
        }

        $request->setUserResolver(fn () => $user);

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