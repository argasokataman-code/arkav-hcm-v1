<?php

namespace App\Http\Controllers\Api\Employee\Concerns;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\CompanyUser;
use App\Models\EmployeeProfile;
use App\Modelsser;
use App\Services\Media\Exceptions\InvalidMediaException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

trait HandlesEmployeeProfilePhotoEndpoints
{
    public function uploadProfilePhoto(Request $request, int $id): JsonResponse
    {
        $auth = $request->user();
        $authId = (int) ($auth?->id ?? 0);

        if ($authId !== $id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'AUTH_FORBIDDEN',
                    'message' => 'You can only update your own profile photo.',
                ],
            ], 403);
        }

        $user = User::query()->find($id);
        if (! $user) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'EMPLOYEE_NOT_FOUND',
                    'message' => 'Employee not found.',
                ],
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'photo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,gif', 'max:2048'],
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_MEDIA',
                    'message' => (string) ($validator->errors()->first('photo') ?: 'Invalid profile photo file.'),
                ],
            ], 422);
        }

        $validated = $validator->validated();

        $activeCompanyId = $this->activeCompanyId($request);
        $profileQuery = EmployeeProfile::query()->where('user_id', $user->id);
        if ($activeCompanyId) {
            $profileQuery->where('company_id', $activeCompanyId);
        }
        $profile = $profileQuery->first();
        if (! $profile) {
            $profile = EmployeeProfile::query()->where('user_id', $user->id)->first();
        }
        if (! $profile) {
            $activeCompany = $activeCompanyId ? Company::query()->find($activeCompanyId) : null;
            if ($activeCompany && (int) $activeCompany->owner_user_id === (int) $user->id) {
                $previousPath = CompanySetting::query()
                    ->where('company_id', $activeCompany->id)
                    ->where('key', 'owner_profile_photo_path')
                    ->value('value');
                try {
                    $stored = $this->avatarStorage->replace((string) ($previousPath ?? ''), $validated['photo'], $user->id);
                } catch (InvalidMediaException $exception) {
                    return response()->json([
                        'success' => false,
                        'error' => ['code' => 'INVALID_MEDIA', 'message' => $exception->getMessage()],
                    ], 422);
                }
                CompanySetting::query()->updateOrCreate(
                    ['company_id' => $activeCompany->id, 'key' => 'owner_profile_photo_path'],
                    ['value' => $stored->path, 'type' => 'string']
                );

                return response()->json([
                    'success' => true,
                    'data' => ['profilePhotoUrl' => $this->profilePhotoUrl($stored->path)],
                ]);
            }

            return response()->json([
                'success' => false,
                'error' => ['code' => 'EMPLOYEE_NOT_FOUND', 'message' => 'Employee profile not found.'],
            ], 404);
        }

        try {
            $stored = $this->avatarStorage->replace(
                $profile->profile_photo_path,
                $validated['photo'],
                $user->id,
            );
        } catch (InvalidMediaException $exception) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_MEDIA',
                    'message' => $exception->getMessage(),
                ],
            ], 422);
        }

        $profile->update(['profile_photo_path' => $stored->path]);

        return response()->json([
            'success' => true,
            'data' => [
                'profilePhotoUrl' => $this->profilePhotoUrl($stored->path),
            ],
        ]);
    }

    public function deleteProfilePhoto(Request $request, int $id): JsonResponse
    {
        $auth = $request->user();
        $authId = (int) ($auth?->id ?? 0);

        if ($authId !== $id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'AUTH_FORBIDDEN',
                    'message' => 'You can only delete your own profile photo.',
                ],
            ], 403);
        }

        $user = User::query()->find($id);
        if (! $user) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'EMPLOYEE_NOT_FOUND',
                    'message' => 'Employee not found.',
                ],
            ], 404);
        }

        $activeCompanyId = $this->activeCompanyId($request);
        $profileQuery = EmployeeProfile::query()->where('user_id', $user->id);
        if ($activeCompanyId) {
            $profileQuery->where('company_id', $activeCompanyId);
        }
        $profile = $profileQuery->first();
        if (! $profile) {
            $profile = EmployeeProfile::query()->where('user_id', $user->id)->first();
        }
        if (! $profile) {
            $activeCompany = $activeCompanyId ? Company::query()->find($activeCompanyId) : null;
            if ($activeCompany && (int) $activeCompany->owner_user_id === (int) $user->id) {
                $setting = CompanySetting::query()
                    ->where('company_id', $activeCompany->id)
                    ->where('key', 'owner_profile_photo_path')
                    ->first();
                if ($setting) {
                    $this->mediaFileDeleter->delete($setting->value);
                    $setting->update(['value' => null]);
                }

                return response()->json([
                    'success' => true,
                    'data' => ['profilePhotoUrl' => null],
                ]);
            }

            return response()->json([
                'success' => false,
                'error' => ['code' => 'EMPLOYEE_NOT_FOUND', 'message' => 'Employee profile not found.'],
            ], 404);
        }

        $this->mediaFileDeleter->delete($profile->profile_photo_path);
        $profile->update(['profile_photo_path' => null]);

        return response()->json([
            'success' => true,
            'data' => [
                'profilePhotoUrl' => null,
            ],
        ]);
    }
}
