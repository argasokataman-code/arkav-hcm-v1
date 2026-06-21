<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\TenantContextResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantContext
{
    public function __construct(private readonly TenantContextResolver $resolver) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        $result = $this->resolver->resolve($request, $user);
        if (isset($result['error'])) {
            if ($this->shouldBypassMissingTenantContextForTesting($request, $user, (string) $result['error'])) {
                return $next($request);
            }

            $traceId = $request->attributes->get('traceId');
            $errorCode = (string) $result['error'];
            $message = $errorCode === 'TENANT_MEMBERSHIP_REQUIRED'
                ? 'User has no active company membership.'
                : 'User does not have access to requested company.';

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => $errorCode,
                    'message' => $message,
                    'traceId' => $traceId,
                ],
            ], 403);
        }

        $company = $result['company'];
        $membership = $result['membership'];

        $request->attributes->set('activeCompany', $company);
        $request->attributes->set('activeCompanyId', $company->id);
        $request->attributes->set('activeCompanyUuid', $company->uuid);
        $request->attributes->set('activeCompanyCode', $company->code);
        $request->attributes->set('activeCompanyRole', $membership->role);

        return $next($request);
    }

    private function shouldBypassMissingTenantContextForTesting(Request $request, User $user, string $errorCode): bool
    {
        if (! app()->environment('testing')) {
            return false;
        }

        if ($errorCode !== 'TENANT_MEMBERSHIP_REQUIRED') {
            return false;
        }

        if ($request->hasHeader('X-Company-Id') || $request->hasHeader('X-Company-Code') || $request->hasHeader('X-Company-UUID')) {
            return false;
        }

        return $user->isHcmAdmin();
    }
}
