<?php

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

use App\Http\Middleware\ApplyLocalizationSettings;
use App\Http\Middleware\AuthenticateApiToken;
use App\Http\Middleware\EnsureAssetManagementWebAccess;
use App\Http\Middleware\EnsureCompanyFeatureForApi;
use App\Http\Middleware\EnsureCompanyFeatureForWebPage;
use App\Http\Middleware\EnsureEmployeeScopedWebPage;
use App\Http\Middleware\EnsureGlobalHcmApiAdmin;
use App\Http\Middleware\EnsureGlobalHcmWebAdminPage;
use App\Http\Middleware\EnsureHcmWebAdminPage;
use App\Http\Middleware\EnsureHcmWebPagesAuthenticated;
use App\Http\Middleware\EnsurePrimarySuperAdminCodeOnePage;
use App\Http\Middleware\HandleCorsRequests;
use App\Http\Middleware\RequiresBiometricConsent;
use App\Http\Middleware\ResolveTenantContext;
use App\Http\Middleware\SecurityHeadersMiddleware;
use App\Http\Middleware\TraceIdMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: '',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Trust reverse-proxy headers (ngrok/load balancer) so generated URLs keep HTTPS scheme.
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_AWS_ELB
        );

        // Replace default EncryptCookies with custom one that excludes arcav_access_token
        $middleware->encryptCookies(except: ['arcav_access_token']);

        $middleware->append(TraceIdMiddleware::class);
        $middleware->append(HandleCorsRequests::class);
        $middleware->append(SecurityHeadersMiddleware::class);
        $middleware->append(ApplyLocalizationSettings::class);
        $middleware->web(append: [
            EnsureHcmWebPagesAuthenticated::class,
        ]);
        $middleware->alias([
            'api.token' => AuthenticateApiToken::class,
            'tenant.context' => ResolveTenantContext::class,
            'hcm.api.global-admin' => EnsureGlobalHcmApiAdmin::class,
            'hcm.api.feature' => EnsureCompanyFeatureForApi::class,
            'hcm.web.admin' => EnsureHcmWebAdminPage::class,
            'hcm.web.global-admin' => EnsureGlobalHcmWebAdminPage::class,
            'hcm.web.primary-super-admin' => EnsurePrimarySuperAdminCodeOnePage::class,
            'hcm.web.asset-management' => EnsureAssetManagementWebAccess::class,
            'hcm.web.feature' => EnsureCompanyFeatureForWebPage::class,
            'hcm.web.employee' => EnsureEmployeeScopedWebPage::class,
            'biometric.consent' => RequiresBiometricConsent::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->is('v1/*')) {
                return null;
            }

            $traceId = (string) ($request->attributes->get('traceId') ?? '');

            $details = [];
            foreach ($e->errors() as $field => $messages) {
                foreach ((array) $messages as $message) {
                    $details[] = [
                        'field' => (string) $field,
                        'rule' => 'validation',
                        'message' => (string) $message,
                    ];
                }
            }

            return response()->json([
                'success' => false,
                // Backward-compatible map for tests/helpers that expect default Laravel validation shape.
                'errors' => $e->errors(),
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Validation failed',
                    'details' => $details,
                    'traceId' => $traceId !== '' ? $traceId : (string) Str::uuid(),
                ],
            ], 422);
        });
    })->create();
