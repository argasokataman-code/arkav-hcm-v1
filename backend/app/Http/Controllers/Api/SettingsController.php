<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\WebsiteSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SettingsController extends Controller
{
    private function ensureHcmAdmin(Request $request): ?JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'AUTH_UNAUTHORIZED',
                    'message' => 'Authentication required.',
                ],
            ], 401);
        }

        if (! $user->isHcmAdmin()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'AUTH_FORBIDDEN',
                    'message' => 'Only HCM admin can manage settings.',
                ],
            ], 403);
        }

        return null;
    }

    /**
     * Get all settings by group
     * GET /api/settings?group=prefix
     */
    public function index(Request $request)
    {
        if ($forbidden = $this->ensureHcmAdmin($request)) {
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
        
        return response()->json([
            'success' => true,
            'data' => $settings,
            'group' => $group,
        ]);
    }

    /**
     * Save settings for a group
     * POST /api/settings
     * Body: { group: 'prefix', settings: { prefix_employee: 'Emp-', prefix_invoice: 'Inv-', ... } }
     */
    public function store(Request $request)
    {
        if ($forbidden = $this->ensureHcmAdmin($request)) {
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

        return response()->json([
            'success' => true,
            'message' => "Settings for group '{$group}' saved successfully",
            'data' => $saved,
        ], 200);
    }

    /**
     * Get a specific setting by key
     * GET /api/settings/{key}
     */
    public function show(Request $request, string $key)
    {
        if ($forbidden = $this->ensureHcmAdmin($request)) {
            return $forbidden;
        }

        $value = Setting::get($key);
        
        if ($value === null) {
            return response()->json([
                'success' => false,
                'message' => "Setting '{$key}' not found",
            ], 404);
        }

        return response()->json([
            'success' => true,
            'key' => $key,
            'value' => $value,
        ]);
    }

    /**
     * Update a specific setting
     * PUT /api/settings/{key}
     */
    public function update(Request $request, string $key)
    {
        if ($forbidden = $this->ensureHcmAdmin($request)) {
            return $forbidden;
        }

        $validated = $request->validate([
            'value' => 'required',
            'group' => 'string|in:general,prefix,business,localization,seo',
        ]);

        $group = $validated['group'] ?? 'general';
        Setting::set($key, $validated['value'], $group);

        return response()->json([
            'success' => true,
            'message' => "Setting '{$key}' updated successfully",
            'key' => $key,
            'value' => $validated['value'],
        ]);
    }

    /**
     * Remove a specific resource from storage
     */
    public function destroy(Request $request, string $key)
    {
        if ($forbidden = $this->ensureHcmAdmin($request)) {
            return $forbidden;
        }

        $setting = Setting::where('key', $key)->first();

        if (!$setting) {
            return response()->json([
                'success' => false,
                'message' => "Setting '{$key}' not found",
            ], 404);
        }

        Setting::forget($key);

        return response()->json([
            'success' => true,
            'message' => "Setting '{$key}' deleted successfully",
        ]);
    }

    /**
     * Upload business branding image and persist path into settings.
     * POST /api/v1/hcm/settings/upload
     */
    public function upload(Request $request)
    {
        if ($forbidden = $this->ensureHcmAdmin($request)) {
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

        return response()->json([
            'success' => true,
            'message' => 'Branding file uploaded successfully',
            'data' => [
                'field' => $field,
                'path' => $storedPath,
                'url' => Storage::disk('public')->url($storedPath),
            ],
        ]);
    }
}

