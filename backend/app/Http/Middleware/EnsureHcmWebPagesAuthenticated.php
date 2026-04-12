<?php

namespace App\Http\Middleware;

use App\Support\ArcavAccessTokenResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureHcmWebPagesAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->pathRequiresToken($request)) {
            return $next($request);
        }

        $token = ArcavAccessTokenResolver::validTokenFromRequest($request);
        if ($token && $token->user) {
            $request->setUserResolver(fn () => $token->user);

            return $next($request);
        }

        // Legacy web login (custom-login / session guard) tanpa cookie API — tetap boleh render halaman.
        if (Auth::check()) {
            return $next($request);
        }

        // Layout terpisah (bukan mainlayout): Route::is('error-404') tidak pernah true di sini;
        // pakai view tamu agar tidak membocorkan sidebar/header aplikasi.
        return response()
            ->view('error-404-guest', [], 404)
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
}
