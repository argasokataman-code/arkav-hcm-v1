<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Models\Setting;
use App\Support\WebsiteSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SettingsController extends Controller
{
    use ChecksPermissions;

    private function apiSuccess(array $data = [], ?string $message = null, int $status = 200): JsonResponse
    {
        $payload = ['success' => true, 'data' => $data];

        if ($message !== null && $message !== '') {
            $payload['message'] = $message;
        }

        return response()->json($payload, $status);
    }

    private function apiError(string $code, string $message, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], $status);
    }

    /**
     * Get all settings by group
     * GET /api/settings?group=prefix
     */
    public function index(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'settings.manage')) {
            return $forbidden;
        }

        $group = $request->get('group', 'general');
        $settings = match ($group) {
            'prefix' => WebsiteSettings::allPrefixSettings(),
            'localization' => WebsiteSettings::allLocalizationSettings(),
            'business' => array_merge(
                WebsiteSettings::allBusinessSettings(),
                WebsiteSettings::allBusinessBrandingPaths(),
            ),
            default => Setting::getByGroup($group),
        };
        
        return $this->apiSuccess($settings);
    }

    /**
     * Save settings for a group
     * POST /api/settings
     * Body: { group: 'prefix', settings: { prefix_employee: 'Emp-', prefix_invoice: 'Inv-', ... } }
     */
    public function store(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'settings.manage')) {
            return $forbidden;
        }

        $validated = $request->validate([
            'group' => 'required|string|in:general,prefix,business,localization,seo',
            'settings' => 'required|array',
        ]);

        $group = $validated['group'];
        $settings = $validated['settings'];

        $saved = [];
        foreach ($settings as $key => $value) {
            Setting::set("{$group}_{$key}", $value, $group);
            $saved[$key] = $value;
        }

        $responseSettings = match ($group) {
            'prefix' => WebsiteSettings::allPrefixSettings(),
            'localization' => WebsiteSettings::allLocalizationSettings(),
            'business' => array_merge(
                WebsiteSettings::allBusinessSettings(),
                WebsiteSettings::allBusinessBrandingPaths(),
            ),
            default => $saved,
        };

        return $this->apiSuccess(
            $responseSettings,
            "Settings for group '{$group}' saved successfully"
        );
    }

    /**
     * Get a specific setting by key
     * GET /api/settings/{key}
     */
    public function show(Request $request, string $key): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'settings.manage')) {
            return $forbidden;
        }

        $value = Setting::get($key);
        
        if ($value === null) {
            return $this->apiError('SETTING_NOT_FOUND', "Setting '{$key}' not found", 404);
        }

        return $this->apiSuccess([
            'key' => $key,
            'value' => $value,
        ]);
    }

    /**
     * Update a specific setting
     * PUT /api/settings/{key}
     */
    public function update(Request $request, string $key): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'settings.manage')) {
            return $forbidden;
        }

        $validated = $request->validate([
            'value' => 'required',
            'group' => 'string|in:general,prefix,business,localization,seo',
        ]);

        $group = $validated['group'] ?? 'general';
        Setting::set($key, $validated['value'], $group);

        return $this->apiSuccess(
            [
                'key' => $key,
                'value' => $validated['value'],
                'group' => $group,
            ],
            "Setting '{$key}' updated successfully"
        );
    }

    /**
     * Remove a specific resource from storage
     */
    public function destroy(Request $request, string $key): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'settings.manage')) {
            return $forbidden;
        }

        $setting = Setting::where('key', $key)->first();

        if (! $setting) {
            return $this->apiError('SETTING_NOT_FOUND', "Setting '{$key}' not found", 404);
        }

        Setting::forget($key);

        return $this->apiSuccess(['key' => $key], "Setting '{$key}' deleted successfully");
    }

    /**
     * Upload business branding image and persist path into settings.
     * POST /api/v1/hcm/settings/upload
     */
    public function upload(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'settings.manage')) {
            return $forbidden;
        }

        $validated = $request->validate([
            'group' => 'required|string|in:business',
            'field' => 'required|string|in:white_logo,dark_logo,white_mini_logo,dark_mini_logo,favicon,apple_icon',
            'file' => 'required|file|mimes:jpg,jpeg,png,webp,svg,ico|max:2048',
        ]);

        $field = $validated['field'];
        $file = $request->file('file');
        $ext = strtolower((string) $file->getClientOriginalExtension());

        $oldPath = Setting::get("business_{$field}_path");
        if (is_string($oldPath) && $oldPath !== '' && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        $filename = 'business_'.$field.'_'.Str::lower(Str::random(12)).'.'.$ext;
        $storedPath = Storage::disk('public')->putFileAs('settings/branding', $file, $filename);

        Setting::set("business_{$field}_path", $storedPath, 'business');

        return $this->apiSuccess(
            [
                'field' => $field,
                'path' => $storedPath,
                'url' => Storage::disk('public')->url($storedPath),
            ],
            'Branding file uploaded successfully'
        );
    }
}

