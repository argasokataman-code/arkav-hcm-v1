<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class TraceIdMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $traceId = $request->header('X-Trace-Id');

        if (! is_string($traceId) || ! preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/', $traceId)) {
            $traceId = (string) Str::uuid();
        }

        $request->attributes->set('traceId', $traceId);

        $response = $next($request);
        $response->headers->set('X-Trace-Id', $traceId);

        return $response;
    }
}
