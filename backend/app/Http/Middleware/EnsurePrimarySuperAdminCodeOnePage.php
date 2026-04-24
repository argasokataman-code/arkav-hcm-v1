<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\ArcavAccessTokenResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restrict sensitive template/catalog pages to primary super admin (code-1)
 * based on configured `hcm.admin_email`.
 */
class EnsurePrimarySuperAdminCodeOnePage
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $this->resolveUser($request);

        if (! $user instanceof User) {
            return redirect()->to(url('login'));
        }

        if (! $this->isPrimarySuperAdminCodeOne($user)) {
            return redirect()->to(url('employee-dashboard'));
        }

        return $next($request);
    }

    private function resolveUser(Request $request): ?User
    {
        $requestUser = $request->user();
        if ($requestUser instanceof User) {
            return $requestUser;
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

    private function isPrimarySuperAdminCodeOne(User $user): bool
    {
        $primaryEmail = strtolower(trim((string) config('hcm.admin_email', 'qa.login@example.com')));
        $userEmail = strtolower(trim((string) ($user->email ?? '')));

        return $userEmail !== '' && $userEmail === $primaryEmail;
    }
}
