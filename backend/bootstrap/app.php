<?php

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\AuthenticateApiToken;
use App\Http\Middleware\EnsureHcmWebAdminPage;
use App\Http\Middleware\EnsureHcmWebPagesAuthenticated;
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
        $middleware->append(SecurityHeadersMiddleware::class);
        $middleware->web(append: [
            EnsureHcmWebPagesAuthenticated::class,
        ]);
        $middleware->alias([
            'api.token' => AuthenticateApiToken::class,
            'tenant.context' => ResolveTenantContext::class,
            'hcm.web.admin' => EnsureHcmWebAdminPage::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
