<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\User;
use App\Support\ArcavAccessTokenResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Halaman employee-only (Blade): role owner/admin akan diarahkan ke halaman admin modul terkait.
 */
class EnsureEmployeeScopedWebPage
{
    public function handle(Request $request, Closure $next, string $adminRedirectPath = 'index'): Response
    {
        $user = $this->resolveUser($request);
        if (! $user instanceof User) {
            return redirect()->to(url('login'));
        }

        $activeRole = $this->resolveActiveCompanyRole($request, $user);
        if (in_array($activeRole, ['employee', 'member'], true)) {
            return $next($request);
        }

        if (in_array($activeRole, ['owner', 'admin'], true)) {
            return redirect()->to(url(trim($adminRedirectPath, '/')));
        }

        return redirect()->to(url('employee-dashboard'));
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

    private function resolveActiveCompanyRole(Request $request, User $user): string
    {
        $activeRole = strtolower(trim((string) $request->attributes->get('activeCompanyRole', '')));
        if ($activeRole !== '') {
            return $activeRole;
        }

        $activeCompany = $request->attributes->get('activeCompany');
        $activeCompanyId = $activeCompany instanceof Company
            ? (int) $activeCompany->id
            : (int) ($request->attributes->get('activeCompanyId') ?? 0);

        if ($activeCompanyId > 0) {
            $role = CompanyUser::query()
                ->where('company_id', $activeCompanyId)
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->value('role');

            return strtolower(trim((string) $role));
        }

        $latestRole = CompanyUser::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->orderByDesc('id')
            ->value('role');

        return strtolower(trim((string) $latestRole));
    }
}
