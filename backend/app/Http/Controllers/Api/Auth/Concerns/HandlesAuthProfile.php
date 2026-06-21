<?php

namespace App\Http\Controllers\Api\Auth\Concerns;

use App\Models\Company;
use App\Models\EmployeeProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

trait HandlesAuthProfile
{
    public function changePassword(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('AUTH_UNAUTHORIZED', 'Unauthorized.', 401, $request);
        }

        $validator = Validator::make($request->all(), [
            'currentPassword' => ['required', 'string', 'max:64'],
            'newPassword' => ['required', 'string', 'regex:/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)[A-Za-z\d@$!%*?&._-]{8,64}$/'],
            'confirmPassword' => ['required', 'same:newPassword'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray(), $request);
        }

        if (! Hash::check((string) $request->input('currentPassword'), $user->password)) {
            return $this->errorResponse('AUTH_INVALID_CREDENTIALS', 'Current password is incorrect.', 422, $request);
        }

        $user->password = Hash::make((string) $request->input('newPassword'));
        $user->save();

        return response()->json([
            'success' => true,
            'data' => ['message' => 'Password changed successfully.'],
        ], 200);
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
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^\+?(?=(?:\D*\d){8,15}\D*$)[0-9\s\-()]+$/'],
            'address' => ['nullable', 'string', 'max:180', 'regex:/^[A-Za-z0-9\s.,\'\/-]{3,180}$/'],
            'addressDetail' => ['nullable', 'string', 'max:60', 'regex:/^[A-Za-z][A-Za-z\s\'.-]{1,59}$/'],
            'companyName' => ['sometimes', 'nullable', 'string', 'min:2', 'max:255'],
            'companyLegalName' => ['sometimes', 'nullable', 'string', 'max:255'],
            'companyAddress' => ['sometimes', 'nullable', 'string', 'max:180', 'regex:/^[A-Za-z0-9\s.,\'\/-]{3,180}$/'],
            'companyCity' => ['sometimes', 'nullable', 'string', 'max:60', 'regex:/^[A-Za-z][A-Za-z\s\'.-]{1,59}$/'],
            'companyState' => ['sometimes', 'nullable', 'string', 'max:60', 'regex:/^[A-Za-z][A-Za-z\s\'.-]{1,59}$/'],
            'companyCountry' => ['sometimes', 'nullable', 'string', 'max:60', 'regex:/^[A-Za-z][A-Za-z\s\'.-]{1,59}$/'],
            'companyPostalCode' => ['sometimes', 'nullable', 'string', 'max:10', 'regex:/^[A-Za-z0-9][A-Za-z0-9\s-]{2,9}$/'],
            'companyNpwp' => ['sometimes', 'nullable', 'string', 'max:32', function ($attribute, $value, $fail) {
                $normalized = $this->normalizeNpwpInput((string) $value);
                if ($normalized !== '' && ! $this->isValidNpwpFormat($normalized)) {
                    $fail('Format NPWP tidak valid. Gunakan 15-16 digit angka.');
                }
            }],
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
        $isOwnerContext = $activeCompany instanceof Company && strtolower(trim($activeCompanyRole)) === 'owner';
        $hasCompanyProfileInput = $request->hasAny([
            'companyName',
            'companyLegalName',
            'companyAddress',
            'companyCity',
            'companyState',
            'companyCountry',
            'companyPostalCode',
            'companyNpwp',
        ]);
        $profile = EmployeeProfile::query()->where('user_id', $user->id)->first();

        if ($profile) {
            $profile->phone = trim((string) $request->input('phone', $profile->phone ?? '')) ?: null;
            $profile->address = trim((string) $request->input('address', $profile->address ?? '')) ?: null;
            $profile->address_detail = trim((string) $request->input('addressDetail', $profile->address_detail ?? '')) ?: null;
            $profile->save();
        } elseif ($isOwnerContext) {
            $this->storeOwnerProfileSettings(
                $activeCompany,
                trim((string) $request->input('phone', '')) ?: null,
                trim((string) $request->input('address', '')) ?: null,
                trim((string) $request->input('addressDetail', '')) ?: null,
            );
        } else {
            // Guard: jangan buat EP zombie untuk user yang sebenarnya adalah owner tenant.
            $isActualOwner = Company::query()->where('owner_user_id', $user->id)->exists();
            if (! $isActualOwner) {
                $profile = EmployeeProfile::query()->firstOrNew(['user_id' => $user->id]);
                if (! $profile->exists && $activeCompany instanceof Company) {
                    $profile->company_id = $activeCompany->id;
                }
                $profile->phone = trim((string) $request->input('phone', $profile->phone ?? '')) ?: null;
                $profile->address = trim((string) $request->input('address', $profile->address ?? '')) ?: null;
                $profile->address_detail = trim((string) $request->input('addressDetail', $profile->address_detail ?? '')) ?: null;
                $profile->save();
            }
        }

        if ($isOwnerContext && $hasCompanyProfileInput) {
            $this->storeOwnerCompanyProfile($request, $activeCompany);
        }

        $user->loadMissing('employeeProfile:id,company_id,user_id,designation,team,phone,address,address_detail,profile_photo_path');
        $resolvedActiveCompany = $activeCompany instanceof Company
            ? Company::query()->with('latestSubscription.package')->find($activeCompany->id)
            : null;
        $companyProfileData = $this->resolveOwnerCompanyProfile($resolvedActiveCompany, $activeCompanyRole);
        $activeCompanyId = $activeCompany instanceof Company ? (int) $activeCompany->id : 0;
        $isTenantHcmAdminForUpdate = $activeCompanyId > 0 ? $user->isHcmAdminForCompany($activeCompanyId) : $user->isHcmAdmin();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'profile' => $this->buildProfilePayload($user, $user->employeeProfile, $resolvedActiveCompany, $activeCompanyRole),
                'currentUserRole' => $this->resolveCurrentUserRole($activeCompanyRole, $user->employeeProfile),
                'subscription' => $isTenantHcmAdminForUpdate ? $this->buildSubscriptionSummary($resolvedActiveCompany) : null,
                'companyProfile' => $companyProfileData,
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
            'profilePhotoUrl' => $this->profilePhotoUrl($profile?->profile_photo_path ?? ($ownerProfile['profilePhotoPath'] ?? null)),
            'source' => $profile ? 'employee_profile' : (! empty($ownerProfile) ? 'company_owner_profile' : 'account'),
        ];
    }

    private function resolveCurrentUserRole(string $activeCompanyRole, ?EmployeeProfile $profile): string
    {
        $normalizedRole = strtolower(trim($activeCompanyRole));
        if ($normalizedRole !== '') {
            return $normalizedRole;
        }

        // Owner yang belum punya employee_profile tetap harus return 'owner', bukan 'user'.
        // Tapi jika activeCompanyRole kosong, kita tidak bisa membedakan — return berdasar EP.
        return $profile ? 'employee' : 'user';
    }

    /**
     * @return array<string, string|null>
     */
    private function normalizeNpwpInput(string $value): string
    {
        return preg_replace('/\D+/', '', trim($value)) ?? '';
    }

    private function isValidNpwpFormat(string $normalizedNpwp): bool
    {
        return preg_match('/^[0-9]{15,16}$/', $normalizedNpwp) === 1;
    }

    /**
     * @return array<string, mixed>|null
     */
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
}
