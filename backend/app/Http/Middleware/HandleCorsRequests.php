<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Handle CORS preflight OPTIONS requests and set appropriate CORS headers.
 */
class HandleCorsRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        // Handle preflight requests
        if ($request->method() === 'OPTIONS') {
            $response = response('', 200);
        } else {
            $response = $next($request);
        }

        // Set CORS headers for API routes
        if ($this->isApiRequest($request)) {
            $allowedOrigins = config('cors.allowed_origins', ['*']);
            $origin = $request->headers->get('Origin');

            if (in_array('*', $allowedOrigins) || in_array($origin, $allowedOrigins)) {
                $response->headers->set('Access-Control-Allow-Origin', $origin ?? '*');
                $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
                $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Company-Code, X-Company-Id, X-Company-UUID, Accept');
                $response->headers->set('Access-Control-Allow-Credentials', 'true');
                $response->headers->set('Access-Control-Max-Age', '3600');
            }
        }

        return $response;
    }

    /**
     * Determine if this is an API request.
     */
    private function isApiRequest(Request $request): bool
    {
        return $request->is('api/*', 'v1/*', 'sanctum/*');
    }
}
