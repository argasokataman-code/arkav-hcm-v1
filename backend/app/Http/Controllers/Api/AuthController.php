<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuthToken;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\CompanyUser;
use App\Models\EmployeeProfile;
use App\Models\Invoice;
use App\Models\HcmPermission;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
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
                'subscription' => $this->buildSubscriptionSummary($resolvedActiveCompany),
                'activeCompany' => $activeCompany ? [
                    'id' => $activeCompany->id,
                    'uuid' => $activeCompany->uuid,
                    'code' => $activeCompany->code,
                    'name' => $activeCompany->name,
                    'role' => $activeCompanyRole,
                ] : null,
            ],
        ]);
    }

    /**
     * Get all permissions for global admin (super user has access to EVERYTHING)
     * Audit Result: 257 comprehensive permissions across ALL 48 modules
     * @return array<string, bool>
     */
    private function getAllPermissionsForGlobalAdmin(): array
    {
        $permissions = $this->legacyGlobalAdminFallbackPermissions();

        if (Schema::hasTable('hcm_permissions')) {
            HcmPermission::query()
                ->where('is_active', true)
                ->pluck('code')
                ->map(static fn ($code): string => (string) $code)
                ->each(function (string $code) use (&$permissions): void {
                    $permissions[$code] = true;
                });
        }

        foreach ($this->globalAdminPermissionAliases() as $source => $aliases) {
            if (empty($permissions[$source])) {
                continue;
            }

            foreach ($aliases as $alias) {
                $permissions[$alias] = true;
            }
        }

        return $permissions;
    }

    /**
     * @return array<string, bool>
     */
    private function legacyGlobalAdminFallbackPermissions(): array
    {
        return [
            // ============ EMPLOYEE MANAGEMENT (8) ============
            'employee.view' => true,
            'employee.create' => true,
            'employee.edit' => true,
            'employee.delete' => true,
            'employee.export' => true,
            'employee.import' => true,
            'employee.admin' => true,
            'employee.lifecycle.view' => true,
            
            // ============ HR & RECRUITMENT (15) ============
            'hr.view' => true,
            'hr.admin' => true,
            'recruitment.view' => true,
            'recruitment.create' => true,
            'recruitment.edit' => true,
            'recruitment.delete' => true,
            'candidate.view' => true,
            'candidate.create' => true,
            'candidate.edit' => true,
            'candidate.delete' => true,
            'job.view' => true,
            'job.create' => true,
            'job.edit' => true,
            'referral.view' => true,
            'offer.view' => true,
            
            // ============ ATTENDANCE & TIME TRACKING (20) ============
            'attendance.view' => true,
            'attendance.create' => true,
            'attendance.edit' => true,
            'attendance.delete' => true,
            'attendance.admin' => true,
            'timesheet.view' => true,
            'timesheet.create' => true,
            'timesheet.edit' => true,
            'timesheet.delete' => true,
            'timesheet.admin' => true,
            'schedule.view' => true,
            'schedule.create' => true,
            'schedule.edit' => true,
            'schedule.admin' => true,
            'shift.view' => true,
            'shift.create' => true,
            'shift.edit' => true,
            'shift.delete' => true,
            'shift.admin' => true,
            'overtime.view' => true,
            
            // ============ LEAVE MANAGEMENT (6) ============
            'leave.view' => true,
            'leave.create' => true,
            'leave.edit' => true,
            'leave.delete' => true,
            'leave.approve' => true,
            'leave.admin' => true,
            
            // ============ FINANCE & PAYROLL (20) ============
            'finance.view' => true,
            'finance.admin' => true,
            'payroll.view' => true,
            'payroll.create' => true,
            'payroll.edit' => true,
            'payroll.delete' => true,
            'payroll.disburse' => true,
            'payroll.admin' => true,
            'salary.view' => true,
            'salary.create' => true,
            'salary.edit' => true,
            'salary.admin' => true,
            'salary.template' => true,
            'salary.delete' => true,
            'overtime.approve' => true,
            'overtime.admin' => true,
            'deduction.view' => true,
            'deduction.manage' => true,
            'thr.manage' => true,
            'provident.manage' => true,
            
            // ============ PERFORMANCE MANAGEMENT (6) ============
            'performance.view' => true,
            'performance.create' => true,
            'performance.edit' => true,
            'performance.delete' => true,
            'performance.admin' => true,
            'goal.manage' => true,
            
            // ============ TRAINING & DEVELOPMENT (5) ============
            'training.view' => true,
            'training.create' => true,
            'training.edit' => true,
            'training.delete' => true,
            'training.admin' => true,
            
            // ============ EMPLOYEE LIFECYCLE (5) ============
            'employee.lifecycle.create' => true,
            'employee.lifecycle.approve' => true,
            'promotion.admin' => true,
            'resignation.admin' => true,
            'termination.admin' => true,
            
            // ============ ASSET MANAGEMENT (5) ============
            'asset.view' => true,
            'asset.create' => true,
            'asset.edit' => true,
            'asset.delete' => true,
            'asset.admin' => true,
            
            // ============ USER & ROLE MANAGEMENT (13) ============
            'user.view' => true,
            'user.create' => true,
            'user.edit' => true,
            'user.delete' => true,
            'user.admin' => true,
            'role.view' => true,
            'role.create' => true,
            'role.edit' => true,
            'role.delete' => true,
            'role.admin' => true,
            'permission.view' => true,
            'permission.manage' => true,
            'permission.assign' => true,
            
            // ============ COMPANY MANAGEMENT (5) ============
            'company.view' => true,
            'company.create' => true,
            'company.edit' => true,
            'company.delete' => true,
            'company.admin' => true,
            
            // ============ SETTINGS & CONFIGURATION (28) ============
            'setting.view' => true,
            'setting.edit' => true,
            'setting.admin' => true,
            'email.view' => true,
            'email.send' => true,
            'email.template' => true,
            'email.admin' => true,
            'sms.view' => true,
            'sms.send' => true,
            'sms.template' => true,
            'sms.admin' => true,
            'otp.manage' => true,
            'salary.admin' => true,
            'approval.view' => true,
            'approval.admin' => true,
            'language.view' => true,
            'language.create' => true,
            'language.edit' => true,
            'language.admin' => true,
            'appearance.view' => true,
            'appearance.edit' => true,
            'appearance.admin' => true,
            'storage.admin' => true,
            'security.ban-ip' => true,
            'cache.manage' => true,
            'cronjob.view' => true,
            'seo.admin' => true,
            'auth.admin' => true,
            
            // ============ REPORTING (6) ============
            'report.view' => true,
            'report.create' => true,
            'report.export' => true,
            'report.schedule' => true,
            'analytics.view' => true,
            'analytics.export' => true,
            
            // ============ SYSTEM MANAGEMENT (8) ============
            'system.admin' => true,
            'backup.create' => true,
            'backup.restore' => true,
            'log.view' => true,
            'audit.view' => true,
            'ai.admin' => true,
            'ai.settings' => true,
            'gdpr.manage' => true,
            
            // ============ PREFIX & CUSTOM FIELDS (7) ============
            'prefix.view' => true,
            'prefix.edit' => true,
            'prefix.admin' => true,
            'customfield.view' => true,
            'customfield.create' => true,
            'customfield.edit' => true,
            'customfield.admin' => true,

            // ============ TICKETS & SUPPORT (6) ============
            'tickets.manage' => true,
            'tickets.admin' => true,
            'ticket.admin' => true,
            'ticket.assign' => true,
            'ticket.update' => true,
            'ticket.category.manage' => true,
            
            // ============ CRM - CLIENTS (5) ============
            'crm.view' => true,
            'crm.admin' => true,
            'client.view' => true,
            'client.create' => true,
            'client.edit' => true,
            
            // ============ CRM - CONTACTS (5) ============
            'contact.view' => true,
            'contact.create' => true,
            'contact.edit' => true,
            'contact.delete' => true,
            'contact.admin' => true,
            
            // ============ CRM - DEALS (5) ============
            'deal.view' => true,
            'deal.create' => true,
            'deal.edit' => true,
            'deal.delete' => true,
            'deal.admin' => true,
            
            // ============ CRM - LEADS (5) ============
            'lead.view' => true,
            'lead.create' => true,
            'lead.edit' => true,
            'lead.delete' => true,
            'lead.admin' => true,
            
            // ============ PROJECTS & TASKS (9) ============
            'project.view' => true,
            'project.create' => true,
            'project.edit' => true,
            'project.delete' => true,
            'project.admin' => true,
            'task.view' => true,
            'task.create' => true,
            'task.edit' => true,
            'task.delete' => true,
            
            // ============ ACCOUNTING - INVOICES (5) ============
            'invoice.view' => true,
            'invoice.create' => true,
            'invoice.edit' => true,
            'invoice.delete' => true,
            'invoice.admin' => true,
            
            // ============ ACCOUNTING - PAYMENTS (5) ============
            'payment.view' => true,
            'payment.create' => true,
            'payment.edit' => true,
            'payment.delete' => true,
            'payment.admin' => true,
            
            // ============ ACCOUNTING - EXPENSES (6) ============
            'expense.view' => true,
            'expense.create' => true,
            'expense.edit' => true,
            'expense.delete' => true,
            'expense.approve' => true,
            'expense.admin' => true,
            
            // ============ ACCOUNTING - ESTIMATES (4) ============
            'estimate.view' => true,
            'estimate.create' => true,
            'estimate.edit' => true,
            'estimate.delete' => true,
            
            // ============ ACCOUNTING - BUDGETS (5) ============
            'budget.view' => true,
            'budget.create' => true,
            'budget.edit' => true,
            'budget.delete' => true,
            'budget.admin' => true,
            
            // ============ ACCOUNTING - TAXES & CATEGORIES (5) ============
            'tax.view' => true,
            'tax.edit' => true,
            'tax.admin' => true,
            'category.view' => true,
            'category.admin' => true,
            
            // ============ COMMUNICATION (5) ============
            'chat.view' => true,
            'chat.send' => true,
            'call.view' => true,
            'call.make' => true,
            'communication.admin' => true,
            
            // ============ PRODUCTIVITY (16) ============
            'calendar.view' => true,
            'calendar.create' => true,
            'calendar.edit' => true,
            'todo.view' => true,
            'todo.create' => true,
            'todo.edit' => true,
            'todo.delete' => true,
            'note.view' => true,
            'note.create' => true,
            'note.edit' => true,
            'note.delete' => true,
            'social.view' => true,
            'social.post' => true,
            'file.view' => true,
            'file.upload' => true,
            'file.delete' => true,
            
            // ============ SAAS MANAGEMENT (8) ============
            'saas.view' => true,
            'saas.admin' => true,
            'saas.package.view' => true,
            'saas.package.create' => true,
            'saas.subscription.view' => true,
            'saas.subscription.approve' => true,
            'saas.billing.view' => true,
            'saas.report.view' => true,
            
            // ============ CONTENT MANAGEMENT (13) ============
            'content.view' => true,
            'content.create' => true,
            'content.edit' => true,
            'content.delete' => true,
            'content.publish' => true,
            'blog.view' => true,
            'blog.create' => true,
            'blog.edit' => true,
            'blog.delete' => true,
            'blog.admin' => true,
            'page.view' => true,
            'page.create' => true,
            'page.edit' => true,
            'page.delete' => true,
            'knowledgebase.view' => true,
            'knowledgebase.create' => true,
            'knowledgebase.edit' => true,
            'knowledgebase.admin' => true,
            
            // ============ ADDITIONAL ADMIN PERMISSIONS ============
            'admin' => true,
            'superadmin' => true,
            'client.delete' => true,
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function globalAdminPermissionAliases(): array
    {
        return [
            'attendance.edit' => ['attendance.update'],
            'employee.edit' => ['employee.update'],
            'leave.edit' => ['leave.update'],
            'overtime.admin' => ['overtime.type.manage'],
            'payroll.edit' => ['payroll.update'],
            'promotion.admin' => ['promotion.manage'],
            'resignation.admin' => ['resignation.manage'],
            'permission.manage' => ['role.sync_permission', 'user_management.manage'],
            'permission.assign' => ['role.sync_permission'],
            'permission.view' => ['role.sync_permission'],
            'role.admin' => ['user_management.manage'],
            'role.edit' => ['role.update'],
            'setting.edit' => ['settings.manage'],
            'setting.admin' => ['settings.manage'],
            'setting.view' => ['settings.view'],
            'termination.admin' => ['termination.manage'],
            'training.admin' => ['training.manage'],
            'user.admin' => ['user.assign_role', 'user_management.manage'],
            'user.edit' => ['user.update'],
        ];
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
            // nosemgrep: php.laravel.security.laravel-unsafe-validator.laravel-unsafe-validator
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
        $activeCompanyRole = (string) ($request->attributes->get('activeCompanyRole') ?? '');
        $profile = EmployeeProfile::query()->where('user_id', $user->id)->first();

        if ($profile) {
            $profile->phone = trim((string) $request->input('phone', $profile->phone ?? '')) ?: null;
            $profile->address = trim((string) $request->input('address', $profile->address ?? '')) ?: null;
            $profile->address_detail = trim((string) $request->input('addressDetail', $profile->address_detail ?? '')) ?: null;
            $profile->save();
        } elseif ($activeCompany instanceof Company && $activeCompanyRole === 'owner') {
            $this->storeOwnerProfileSettings(
                $activeCompany,
                trim((string) $request->input('phone', '')) ?: null,
                trim((string) $request->input('address', '')) ?: null,
                trim((string) $request->input('addressDetail', '')) ?: null,
            );
        } else {
            $profile = EmployeeProfile::query()->firstOrNew(['user_id' => $user->id]);
            if (! $profile->exists && $activeCompany instanceof Company) {
                $profile->company_id = $activeCompany->id;
            }
            $profile->phone = trim((string) $request->input('phone', $profile->phone ?? '')) ?: null;
            $profile->address = trim((string) $request->input('address', $profile->address ?? '')) ?: null;
            $profile->address_detail = trim((string) $request->input('addressDetail', $profile->address_detail ?? '')) ?: null;
            $profile->save();
        }

        $user->loadMissing('employeeProfile:id,company_id,user_id,designation,team,phone,address,address_detail,profile_photo_path');
        $resolvedActiveCompany = $activeCompany instanceof Company
            ? Company::query()->with('latestSubscription.package')->find($activeCompany->id)
            : null;

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'profile' => $this->buildProfilePayload($user, $user->employeeProfile, $resolvedActiveCompany, $activeCompanyRole),
                'currentUserRole' => $this->resolveCurrentUserRole($activeCompanyRole, $user->employeeProfile),
                'subscription' => $this->buildSubscriptionSummary($resolvedActiveCompany),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildProfilePayload(User $user, ?EmployeeProfile $profile, ?Company $activeCompany, string $activeCompanyRole): array
    {
        [$firstName, $lastName] = $this->splitName((string) ($user->name ?? ''));
        $ownerProfile = $this->resolveOwnerProfileSettings($activeCompany, $activeCompanyRole);

        return [
            'firstName' => $firstName,
            'lastName' => $lastName,
            'phone' => $profile?->phone ?? ($ownerProfile['phone'] ?? null),
            'address' => $profile?->address ?? ($ownerProfile['address'] ?? null),
            'addressDetail' => $profile?->address_detail ?? ($ownerProfile['addressDetail'] ?? null),
            'designation' => $profile?->designation,
            'team' => $profile?->team,
            'profilePhotoUrl' => $this->profilePhotoUrl($profile?->profile_photo_path),
            'source' => $profile ? 'employee_profile' : (! empty($ownerProfile) ? 'company_owner_profile' : 'account'),
        ];
    }

    private function resolveCurrentUserRole(string $activeCompanyRole, ?EmployeeProfile $profile): string
    {
        $normalizedRole = strtolower(trim($activeCompanyRole));
        if ($normalizedRole !== '') {
            return $normalizedRole;
        }

        return $profile ? 'employee' : 'user';
    }

    /**
     * @return array<string, string|null>
     */
    private function resolveOwnerProfileSettings(?Company $activeCompany, string $activeCompanyRole): array
    {
        if (! $activeCompany || strtolower(trim($activeCompanyRole)) !== 'owner') {
            return [];
        }

        $settings = CompanySetting::query()
            ->where('company_id', $activeCompany->id)
            ->whereIn('key', ['owner_phone', 'owner_address', 'owner_address_detail'])
            ->pluck('value', 'key');

        return [
            'phone' => $settings->get('owner_phone'),
            'address' => $settings->get('owner_address'),
            'addressDetail' => $settings->get('owner_address_detail'),
        ];
    }

    private function storeOwnerProfileSettings(Company $company, ?string $phone, ?string $address, ?string $addressDetail): void
    {
        $settings = [
            'owner_phone' => $phone,
            'owner_address' => $address,
            'owner_address_detail' => $addressDetail,
        ];

        foreach ($settings as $key => $value) {
            CompanySetting::query()->updateOrCreate(
                ['company_id' => $company->id, 'key' => $key],
                ['value' => $value, 'type' => 'string']
            );
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildSubscriptionSummary(?Company $activeCompany): ?array
    {
        if (! $activeCompany) {
            return null;
        }

        $subscription = $activeCompany->latestSubscription;
        if (! $subscription) {
            return null;
        }

        $nextInvoice = Invoice::query()
            ->where('company_id', $activeCompany->id)
            ->where('subscription_id', $subscription->id)
            ->where('is_paid', false)
            ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
            ->latest('due_date')
            ->latest('issue_date')
            ->first();

        $nextPaymentDate = $nextInvoice?->due_date?->toDateString()
            ?? $subscription->ends_at?->toDateString();
        $nextPaymentAmount = $nextInvoice ? (float) $nextInvoice->amount_due : (float) ($subscription->amount ?? 0);
        $employeeLimitFeature = $subscription->package?->getFeature('max_employees');
        $employeeLimit = $employeeLimitFeature?->limit;
        $employeeUsed = EmployeeProfile::query()
            ->where('company_id', $activeCompany->id)
            ->count();

        return [
            'id' => $subscription->id,
            'status' => (string) $subscription->status,
            'planCode' => (string) ($subscription->plan_code ?? ''),
            'packageCode' => (string) ($subscription->package?->code ?? $subscription->plan_code ?? ''),
            'packageName' => (string) ($subscription->package?->name ?? ''),
            'billingCycle' => (string) ($subscription->billing_cycle ?? ''),
            'startsAt' => $subscription->starts_at?->toIso8601String(),
            'endsAt' => $subscription->ends_at?->toIso8601String(),
            'trialEndsAt' => $subscription->trial_ends_at?->toIso8601String(),
            'amount' => (float) ($subscription->amount ?? 0),
            'autoRenew' => (bool) $subscription->auto_renew,
            'nextPayment' => [
                'date' => $nextPaymentDate,
                'amount' => $nextPaymentAmount,
                'source' => $nextInvoice ? 'invoice' : 'subscription_cycle',
                'invoiceId' => $nextInvoice?->id,
                'invoiceNumber' => $nextInvoice?->invoice_number,
                'invoiceStatus' => $nextInvoice?->status,
            ],
            'employeeSlots' => [
                'limit' => $employeeLimit,
                'used' => $employeeUsed,
                'remaining' => $employeeLimit === null ? null : max($employeeLimit - $employeeUsed, 0),
                'isUnlimited' => $employeeLimitFeature !== null && $employeeLimit === null,
                'isConfigured' => $employeeLimitFeature !== null,
            ],
        ];
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
                'owner_user_id' => null,
                'timezone' => (string) config('app.timezone', 'UTC'),
                'currency' => 'IDR',
                'country_code' => 'ID',
            ]
        );

        $legacyCompanyId = $this->resolveLegacyCompanyId($defaultCompany);
        $legacyUserId = $this->resolveLegacyUserId($user);
        if (! $legacyCompanyId || ! $legacyUserId) {
            return;
        }

        CompanyUser::query()->firstOrCreate(
            [
                'company_id' => $legacyCompanyId,
                'user_id' => $legacyUserId,
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

        $legacyUserId = $this->resolveLegacyUserId($user);
        if (! $legacyUserId) {
            return;
        }

        $hasActiveMembership = CompanyUser::query()
            ->where('user_id', $legacyUserId)
            ->where('status', 'active')
            ->exists();

        if ($hasActiveMembership) {
            return;
        }

        $this->attachUserToDefaultCompany($user);
    }

    private function resolveLegacyUserId(User $user): ?int
    {
        $id = $user->getAttribute('id');
        if (is_numeric($id)) {
            return (int) $id;
        }

        $uuid = (string) ($user->getAttribute('uuid') ?? '');
        if ($uuid === '' || ! \Illuminate\Support\Facades\Schema::hasColumn($user->getTable(), 'uuid')) {
            return null;
        }

        $resolved = User::query()->where('uuid', $uuid)->value('id');

        return is_numeric($resolved) ? (int) $resolved : null;
    }

    private function resolveLegacyCompanyId(Company $company): ?int
    {
        $id = $company->getAttribute('id');
        if (is_numeric($id)) {
            return (int) $id;
        }

        $uuid = (string) ($company->getAttribute('uuid') ?? '');
        if ($uuid === '' || ! \Illuminate\Support\Facades\Schema::hasColumn($company->getTable(), 'uuid')) {
            return null;
        }

        $resolved = Company::query()->where('uuid', $uuid)->value('id');

        return is_numeric($resolved) ? (int) $resolved : null;
    }

    /**
     * @return array{company?: Company, role?: string, error?: string}
     */
    private function resolveRequestedCompanyForLogin(Request $request, User $user): array
    {
        $companyCode = trim((string) $request->input('companyCode', ''));
        if ($companyCode === '') {
            if ($this->requiresCompanyModeLogin($user)) {
                return ['error' => 'AUTH_COMPANY_MODE_REQUIRED'];
            }

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

    private function requiresCompanyModeLogin(User $user): bool
    {
        if ($user->isGlobalHcmAdmin()) {
            return false;
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('company_users')) {
            return false;
        }

        return CompanyUser::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->whereIn('role', ['owner', 'admin'])
            ->exists();
    }
}
