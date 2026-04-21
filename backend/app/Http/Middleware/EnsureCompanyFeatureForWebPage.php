<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Models\Subscription;
use App\Models\User;
use App\Support\ArcavAccessTokenResolver;
use App\Support\TenantContextResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Web page guard yang memblokir tenant ketika subscription aktifnya tidak
 * memuat fitur paket tertentu (mis. tickets, payroll, training).
 *
 * Konsisten dengan semantik {@see EnsureAssetManagementWebAccess}: global
 * super admin selalu boleh; tenant tanpa subscription aktif tidak diblok di
 * sini (middleware {@see EnsureHcmWebPagesAuthenticated} sudah mengarahkan
 * pending_payment ke /subscription).
 *
 * Pakai sebagai alias `hcm.web.feature:tickets` (atau lebih dari satu kode
 * dipisah koma untuk policy AND, mis. `hcm.web.feature:tickets,payroll`).
 */
class EnsureCompanyFeatureForWebPage
{
    public function __construct(
        private readonly TenantContextResolver $tenantContextResolver,
    ) {
    }

    public function handle(Request $request, Closure $next, string ...$featureCodes): Response
    {
        $user = $this->resolveUser($request);
        if (! $user instanceof User) {
            return redirect()->to(url('login'));
        }

        if ($user->isGlobalHcmAdmin()) {
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

        $subscription = Subscription::activeForCompany($companyId);
        if (! $subscription) {
            // Tidak ada subscription aktif: serahkan ke gate auth/lifecycle lain
            // (mis. pending_payment redirect). Jangan double-block di sini.
            return $next($request);
        }

        foreach ($codes as $code) {
            if (! $subscription->package?->hasFeature($code)) {
                return redirect()
                    ->to(url('employee-dashboard'))
                    ->with('error', sprintf(
                        'Fitur "%s" belum termasuk dalam paket aktif Anda. Silakan upgrade paket.',
                        $code,
                    ));
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
                    $out[] = $code;
                }
            }
        }

        return array_values(array_unique($out));
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
