<?php

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\AuthenticateApiToken;
use App\Http\Middleware\ApplyLocalizationSettings;
use App\Http\Middleware\EnsureAssetManagementWebAccess;
use App\Http\Middleware\EnsureCompanyFeatureForWebPage;
use App\Http\Middleware\EnsureGlobalHcmWebAdminPage;
use App\Http\Middleware\EnsureHcmWebAdminPage;
use App\Http\Middleware\EnsureHcmWebPagesAuthenticated;
use App\Http\Middleware\EnsurePrimarySuperAdminCodeOnePage;
use App\Http\Middleware\HandleCorsRequests;
use App\Http\Middleware\ResolveTenantContext;
use App\Http\Middleware\SecurityHeadersMiddleware;
use App\Http\Middleware\TraceIdMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: '',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
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
            'hcm.web.admin' => EnsureHcmWebAdminPage::class,
            'hcm.web.global-admin' => EnsureGlobalHcmWebAdminPage::class,
            'hcm.web.primary-super-admin' => EnsurePrimarySuperAdminCodeOnePage::class,
            'hcm.web.asset-management' => EnsureAssetManagementWebAccess::class,
            'hcm.web.feature' => EnsureCompanyFeatureForWebPage::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, \Illuminate\Http\Request $request) {
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
                    'traceId' => $traceId !== '' ? $traceId : (string) \Illuminate\Support\Str::uuid(),
                ],
            ], 422);
        });
    })->create();
