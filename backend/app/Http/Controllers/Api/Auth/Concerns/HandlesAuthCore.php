<?php

namespace App\Http\Controllers\Api\Auth\Concerns;

use App\Mail\RegisterSuccessMailable;
use App\Models\AuthToken;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

trait HandlesAuthCore
{
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'min:2', 'max:150', 'regex:/^[A-Za-z][A-Za-z\s\'.-]{1,149}$/'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'regex:/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)[A-Za-z\d@$!%*?&._-]{8,64}$/'],
            'confirmPassword' => ['required', 'same:password'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray(), $request);
        }

        $user = User::create([
            'name' => $request->string('name'),
            'email' => $request->string('email'),
            'password' => Hash::make((string) $request->input('password')),
        ]);

        $this->attachUserToDefaultCompany($user);
        $this->sendRegisterSuccessEmail($user);
        $legacyUserId = $this->resolveLegacyUserId($user);

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $legacyUserId,
                    'name' => $user->name,
                    'email' => $user->email,
                    'status' => 'active',
                ],
            ],
        ], 201);
    }

    private function sendRegisterSuccessEmail(User $user): void
    {
        if (! filter_var((string) $user->email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        try {
            Mail::to((string) $user->email)->send(new RegisterSuccessMailable($user));
        } catch (\Throwable $e) {
            Log::warning('Failed to send register success email.', [
                'user_id' => $user->id,
                'email' => (string) $user->email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function login(Request $request): JsonResponse
    {
        $throttleKey = $this->loginThrottleKey($request);
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'AUTH_TOO_MANY_ATTEMPTS',
                    'message' => 'Too many login attempts. Please try again later.',
                    'retryAfterSeconds' => $seconds,
                    'traceId' => $request->attributes->get('traceId'),
                ],
            ], 429);
        }

        $validator = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email:rfc'],
            'password' => ['required', 'string', 'min:8', 'max:64'],
            'rememberMe' => ['nullable', 'boolean'],
            'companyCode' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_-]+$/'],
        ]);

        if ($validator->fails()) {
            RateLimiter::hit($throttleKey, 60);

            return $this->validationError($validator->errors()->toArray(), $request);
        }

        $user = User::where('email', $request->string('email'))->first();

        if (! $user || ! Hash::check((string) $request->input('password'), $user->password)) {
            RateLimiter::hit($throttleKey, 60);

            return $this->errorResponse('AUTH_INVALID_CREDENTIALS', 'Invalid credentials.', 401, $request);
        }

        // Backfill tenant membership for legacy users so post-login /auth/me does not fail with TENANT_MEMBERSHIP_REQUIRED.
        $this->ensureUserHasActiveCompanyMembership($user);

        $requestedCompany = $this->resolveRequestedCompanyForLogin($request, $user);
        if (($requestedCompany['error'] ?? null) === 'AUTH_COMPANY_MODE_REQUIRED') {
            return $this->errorResponse('AUTH_COMPANY_MODE_REQUIRED', 'Company owner/admin must login using company code (Login Company mode).', 422, $request);
        }
        if (($requestedCompany['error'] ?? null) === 'TENANT_FORBIDDEN') {
            return $this->errorResponse('TENANT_FORBIDDEN', 'User does not have access to requested company.', 403, $request);
        }

        RateLimiter::clear($throttleKey);

        // Set Laravel session so web routes (which rely on session auth) can access the user.
        // This is necessary for web pages like /subscription that use session-based middleware (hcm.web.admin).
        Auth::login($user, true);

        $plainToken = Str::random(64);
        $rememberMe = (bool) $request->boolean('rememberMe', false);
        $expiresIn = $rememberMe ? $this->rememberTtlSeconds() : $this->defaultTtlSeconds();

        // Revoke all previous tokens before issuing a new one (single active token per user).
        AuthToken::where('user_id', $user->id)->delete();

        AuthToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addSeconds($expiresIn),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'accessToken' => $plainToken,
                'tokenType' => 'Bearer',
                'expiresIn' => $expiresIn,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => ['employee'],
                ],
                'activeCompany' => isset($requestedCompany['company']) ? [
                    'id' => $requestedCompany['company']->id,
                    'uuid' => $requestedCompany['company']->uuid,
                    'code' => $requestedCompany['company']->code,
                    'name' => $requestedCompany['company']->name,
                    'role' => $requestedCompany['role'] ?? null,
                ] : null,
            ],
        ])->cookie(
            $this->cookieName(),
            $plainToken,
            $this->minutesFromSeconds($expiresIn),
            $this->cookiePath(),
            $this->cookieDomain(),
            $this->cookieSecure(),
            true,
            true,  // raw=true to skip encryption
            $this->cookieSameSite()
        );
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var AuthToken|null $token */
        $token = $request->attributes->get('authToken');

        if ($token) {
            $token->update(['revoked_at' => now()]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'message' => 'Logged out successfully',
            ],
        ])->withoutCookie(
            $this->cookieName(),
            $this->cookiePath(),
            $this->cookieDomain()
        );
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        $activeCompany = $request->attributes->get('activeCompany');
        $activeCompanyRole = (string) ($request->attributes->get('activeCompanyRole') ?? '');
        $activeCompanyId = (int) ($request->attributes->get('activeCompanyId') ?? 0);

        if (! $user) {
            return $this->errorResponse('AUTH_UNAUTHORIZED', 'Unauthorized.', 401, $request);
        }

        $user->loadMissing('employeeProfile:id,company_id,user_id,designation,team,phone,address,address_detail,profile_photo_path');
        $profile = $user->employeeProfile;
        $resolvedActiveCompany = $activeCompanyId > 0
            ? Company::query()->with('latestSubscription.package')->find($activeCompanyId)
            : ($activeCompany instanceof Company ? $activeCompany : null);
        $profileData = $this->buildProfilePayload($user, $profile, $resolvedActiveCompany, $activeCompanyRole);
        $companyProfileData = $this->resolveOwnerCompanyProfile($resolvedActiveCompany, $activeCompanyRole);
        $currentUserRole = $this->resolveCurrentUserRole($activeCompanyRole, $profile);

        // `hcmAdmin` is used widely by HCM web modules to decide whether to allow "admin pages".
        // For self-serve trial: the company owner should be treated as tenant-admin for their own company.
        $isGlobalHcmAdmin = $user->isHcmAdmin();
        $isTenantHcmAdmin = $activeCompanyId > 0 ? $user->isHcmAdminForCompany($activeCompanyId) : $isGlobalHcmAdmin;
        $permissions = $user->permissionsForContext($activeCompanyId > 0 ? $activeCompanyId : null);

        // Global admin should always expose the full permission map, even if
        // legacy company-role assignments only return a partial subset.
        if ($isGlobalHcmAdmin) {
            $permissions = array_replace($this->getAllPermissionsForGlobalAdmin(), $permissions);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'profile' => $profileData,
                'roles' => [$currentUserRole],
                'currentUserRole' => $currentUserRole,
                'hcmAdmin' => $isTenantHcmAdmin,
                'hcmGlobalAdmin' => $isGlobalHcmAdmin,
                'permissions' => $permissions,
                'permissionCodes' => array_keys($permissions),
                'subscription' => $isTenantHcmAdmin ? $this->buildSubscriptionSummary($resolvedActiveCompany) : null,
                'activeCompany' => $activeCompany ? [
                    'id' => $activeCompany->id,
                    'uuid' => $activeCompany->uuid,
                    'code' => $activeCompany->code,
                    'name' => $activeCompany->name,
                    'legalName' => $activeCompany->legal_name,
                    'role' => $activeCompanyRole,
                ] : null,
                'companyProfile' => $companyProfileData,
            ],
        ]);
    }

    /**
     * Get all permissions for global admin (super user has access to EVERYTHING)
     * Audit Result: 257 comprehensive permissions across ALL 48 modules
     *
     * @return array<string, bool>
     */
    private function validationError(array $errors, Request $request): JsonResponse
    {
        $details = [];
        foreach ($errors as $field => $messages) {
            foreach ($messages as $message) {
                $details[] = [
                    'field' => $field,
                    'rule' => 'validation',
                    'message' => $message,
                ];
            }
        }

        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => 'Validation failed',
                'details' => $details,
                'traceId' => $request->attributes->get('traceId'),
            ],
        ], 422);
    }

    private function loginThrottleKey(Request $request): string
    {
        $email = strtolower(trim((string) $request->input('email', '')));
        $ip = (string) $request->ip();

        return 'auth:login:'.$email.'|'.$ip;
    }

    private function cookieName(): string
    {
        return (string) config('auth.api_token_cookie.name', 'arcav_access_token');
    }

    private function cookieSameSite(): string
    {
        return (string) config('auth.api_token_cookie.same_site', 'lax');
    }

    private function cookiePath(): string
    {
        return (string) config('auth.api_token_cookie.path', '/');
    }

    private function cookieDomain(): ?string
    {
        $domain = config('auth.api_token_cookie.domain');

        return is_string($domain) && $domain !== '' ? $domain : null;
    }

    private function cookieSecure(): bool
    {
        return (bool) config('auth.api_token_cookie.secure', false);
    }

    private function defaultTtlSeconds(): int
    {
        return max(60, (int) config('auth.api_token_cookie.ttl_seconds', 3600));
    }

    private function rememberTtlSeconds(): int
    {
        return max($this->defaultTtlSeconds(), (int) config('auth.api_token_cookie.remember_ttl_seconds', 2592000));
    }

    private function minutesFromSeconds(int $seconds): int
    {
        return max(1, (int) ceil($seconds / 60));
    }
}
