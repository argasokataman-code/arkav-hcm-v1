<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuthToken;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AuthController extends Controller
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

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'status' => 'active',
                ],
            ],
        ], 201);
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
        if (($requestedCompany['error'] ?? null) === 'TENANT_FORBIDDEN') {
            return $this->errorResponse('TENANT_FORBIDDEN', 'User does not have access to requested company.', 403, $request);
        }

        RateLimiter::clear($throttleKey);

        $plainToken = Str::random(64);
        $rememberMe = (bool) $request->boolean('rememberMe', false);
        $expiresIn = $rememberMe ? $this->rememberTtlSeconds() : $this->defaultTtlSeconds();

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
            false,
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
        $activeCompanyRole = $request->attributes->get('activeCompanyRole');
        $activeCompanyId = (int) ($request->attributes->get('activeCompanyId') ?? 0);

        if (! $user) {
            return $this->errorResponse('AUTH_UNAUTHORIZED', 'Unauthorized.', 401, $request);
        }

        $user->loadMissing('employeeProfile:id,company_id,user_id,designation,team,phone,address,address_detail,profile_photo_path');
        $profile = $user->employeeProfile;
        [$firstName, $lastName] = $this->splitName((string) ($user->name ?? ''));

        // `hcmAdmin` is used widely by HCM web modules to decide whether to allow "admin pages".
        // For self-serve trial: the company owner should be treated as tenant-admin for their own company.
        $isGlobalHcmAdmin = $user->isHcmAdmin();
        $isTenantHcmAdmin = $activeCompanyId > 0 ? $user->isHcmAdminForCompany($activeCompanyId) : $isGlobalHcmAdmin;

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'profile' => [
                    'firstName' => $firstName,
                    'lastName' => $lastName,
                    'phone' => $profile?->phone,
                    'address' => $profile?->address,
                    'addressDetail' => $profile?->address_detail,
                    'designation' => $profile?->designation,
                    'team' => $profile?->team,
                    'profilePhotoUrl' => $this->profilePhotoUrl($profile?->profile_photo_path),
                ],
                'roles' => ['employee'],
                'hcmAdmin' => $isTenantHcmAdmin,
                'hcmGlobalAdmin' => $isGlobalHcmAdmin,
                'activeCompany' => $activeCompany ? [
                    'id' => $activeCompany->id,
                    'code' => $activeCompany->code,
                    'name' => $activeCompany->name,
                    'role' => $activeCompanyRole,
                ] : null,
            ],
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('AUTH_UNAUTHORIZED', 'Unauthorized.', 401, $request);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'min:2', 'max:150', 'regex:/^[A-Za-z][A-Za-z\s\'.-]{1,149}$/'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'addressDetail' => ['nullable', 'string', 'max:500'],
            'currentPassword' => ['nullable', 'string', 'max:64'],
            'newPassword' => ['nullable', 'string', 'regex:/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)[A-Za-z\d@$!%*?&._-]{8,64}$/'],
            'confirmPassword' => ['required_with:newPassword', 'same:newPassword'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray(), $request);
        }

        $newPassword = (string) $request->input('newPassword', '');
        if ($newPassword !== '') {
            $currentPassword = (string) $request->input('currentPassword', '');
            if ($currentPassword === '' || ! Hash::check($currentPassword, $user->password)) {
                return $this->errorResponse('AUTH_INVALID_CREDENTIALS', 'Current password is invalid.', 422, $request);
            }
            $user->password = Hash::make($newPassword);
        }

        $user->name = trim((string) $request->input('name', $user->name));
        $user->email = trim((string) $request->input('email', $user->email));
        $user->save();

        $activeCompany = $request->attributes->get('activeCompany');
        $profile = EmployeeProfile::query()->firstOrNew(['user_id' => $user->id]);
        if (! $profile->exists && $activeCompany instanceof Company) {
            $profile->company_id = $activeCompany->id;
        }
        $profile->phone = trim((string) $request->input('phone', $profile->phone ?? '')) ?: null;
        $profile->address = trim((string) $request->input('address', $profile->address ?? '')) ?: null;
        $profile->address_detail = trim((string) $request->input('addressDetail', $profile->address_detail ?? '')) ?: null;
        $profile->save();

        $user->loadMissing('employeeProfile:id,company_id,user_id,designation,team,phone,address,address_detail,profile_photo_path');
        [$firstName, $lastName] = $this->splitName((string) $user->name);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'profile' => [
                    'firstName' => $firstName,
                    'lastName' => $lastName,
                    'phone' => $user->employeeProfile?->phone,
                    'address' => $user->employeeProfile?->address,
                    'addressDetail' => $user->employeeProfile?->address_detail,
                    'designation' => $user->employeeProfile?->designation,
                    'team' => $user->employeeProfile?->team,
                    'profilePhotoUrl' => $this->profilePhotoUrl($user->employeeProfile?->profile_photo_path),
                ],
            ],
        ]);
    }

    private function splitName(string $name): array
    {
        $clean = trim($name);
        if ($clean === '') {
            return ['', ''];
        }

        $parts = preg_split('/\s+/', $clean) ?: [];
        if (count($parts) <= 1) {
            return [$clean, ''];
        }

        $first = array_shift($parts);

        return [$first, implode(' ', $parts)];
    }

    private function profilePhotoUrl(?string $path): ?string
    {
        $normalized = ltrim((string) $path, '/');
        if ($normalized === '') {
            return null;
        }

        return '/storage/'.$normalized;
    }

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

    private function errorResponse(string $code, string $message, int $status, Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'traceId' => $request->attributes->get('traceId'),
            ],
        ], $status);
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

    private function attachUserToDefaultCompany(User $user): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('companies') || ! \Illuminate\Support\Facades\Schema::hasTable('company_users')) {
            return;
        }

        $defaultCompany = Company::query()->firstOrCreate(
            ['code' => 'default_company'],
            [
                'name' => 'Default Company',
                'legal_name' => 'Default Company',
                'status' => 'active',
                'owner_user_id' => $user->id,
                'timezone' => (string) config('app.timezone', 'UTC'),
                'currency' => 'IDR',
                'country_code' => 'ID',
            ]
        );

        CompanyUser::query()->firstOrCreate(
            [
                'company_id' => $defaultCompany->id,
                'user_id' => $user->id,
            ],
            [
                'role' => 'member',
                'status' => 'active',
                'joined_at' => now(),
                'invited_by_user_id' => null,
            ]
        );
    }

    private function ensureUserHasActiveCompanyMembership(User $user): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('company_users')) {
            return;
        }

        $hasActiveMembership = CompanyUser::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();

        if ($hasActiveMembership) {
            return;
        }

        $this->attachUserToDefaultCompany($user);
    }

    /**
     * @return array{company?: Company, role?: string, error?: string}
     */
    private function resolveRequestedCompanyForLogin(Request $request, User $user): array
    {
        $companyCode = trim((string) $request->input('companyCode', ''));
        if ($companyCode === '') {
            return [];
        }

        $company = Company::query()->where('code', $companyCode)->first();
        if (! $company) {
            return ['error' => 'TENANT_FORBIDDEN'];
        }

        $membership = CompanyUser::query()
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (! $membership) {
            return ['error' => 'TENANT_FORBIDDEN'];
        }

        return [
            'company' => $company,
            'role' => (string) $membership->role,
        ];
    }
}
