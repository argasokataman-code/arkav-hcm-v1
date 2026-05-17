<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Models\Setting;
use App\Support\WebsiteSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SettingsController extends Controller
{
    use ChecksPermissions;

    private const AI_GROUP = 'ai';
    private const AI_SECRET_KEYS = [
        'openai_api_key',
        'api_key',
        'ai_openai_api_key',
        'ai_api_key',
    ];

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
    * GET /api/settings?group=general
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'group' => 'nullable|string|in:general,business,localization,seo,authentication,ai,preferences,custom_code',
        ]);

        $group = (string) ($validated['group'] ?? 'general');
        if ($forbidden = $this->ensureGroupPermission($request, $group)) {
            return $forbidden;
        }

        $settings = match ($group) {
            'localization' => WebsiteSettings::allLocalizationSettings(),
            'business' => array_merge(
                WebsiteSettings::allBusinessSettings(),
                WebsiteSettings::allBusinessBrandingPaths(),
            ),
            default => Setting::getByGroup($group),
        };

        if ($group === self::AI_GROUP) {
            $settings = $this->maskAiSecrets($settings);
        }

        return $this->apiSuccess($settings);
    }

    /**
     * Save settings for a group
     * POST /api/settings
    * Body: { group: 'general', settings: { key: value, ... } }
     */
    public function store(Request $request): JsonResponse
    {
        $requestedGroup = (string) $request->input('group', 'general');
        if ($forbidden = $this->ensureGroupPermission($request, $requestedGroup)) {
            return $forbidden;
        }

        $validated = $request->validate([
            'group' => 'required|string|in:general,business,localization,seo,authentication,ai,preferences,custom_code',
            'settings' => 'required|array',
        ]);

        $group = $validated['group'];
        $settings = $validated['settings'];

        if ($group === 'localization') {
            $this->validateLocalizationPayload($settings);
        }

        if ($group === 'general') {
            $this->validateGeneralPayload($settings);
        }

        if ($group === self::AI_GROUP) {
            $settings = $this->prepareAiSettingsForStorage($settings);
        }

        $saved = [];
        foreach ($settings as $key => $value) {
            Setting::set("{$group}_{$key}", $value, $group);
            $saved[$key] = $value;
        }

        $responseSettings = match ($group) {
            'localization' => WebsiteSettings::allLocalizationSettings(),
            'business' => array_merge(
                WebsiteSettings::allBusinessSettings(),
                WebsiteSettings::allBusinessBrandingPaths(),
            ),
            default => $saved,
        };

        if ($group === self::AI_GROUP) {
            $responseSettings = $this->maskAiSecrets($responseSettings);
        }

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
            'group' => 'string|in:general,business,localization,seo,authentication,ai,preferences,custom_code',
        ]);

        $group = $validated['group'] ?? 'general';

        if ($group === 'localization' && in_array($key, ['localization_timezone', 'locale_timezone'], true)) {
            $this->validateLocalizationPayload([
                'timezone' => $validated['value'],
            ]);
        }

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

    private function ensureGroupPermission(Request $request, string $group): ?JsonResponse
    {
        if ($group === self::AI_GROUP) {
            return $this->ensureAnyPermission($request, ['settings.manage', 'ai.settings']);
        }

        return $this->ensurePermission($request, 'settings.manage');
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    private function maskAiSecrets(array $settings): array
    {
        foreach (self::AI_SECRET_KEYS as $secretKey) {
            if (array_key_exists($secretKey, $settings) && is_string($settings[$secretKey]) && trim($settings[$secretKey]) !== '') {
                $settings[$secretKey] = '********';
            }
        }

        return $settings;
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    private function prepareAiSettingsForStorage(array $settings): array
    {
        foreach (['openai_api_key', 'api_key'] as $secretKey) {
            if (! array_key_exists($secretKey, $settings)) {
                continue;
            }

            $incoming = $settings[$secretKey];
            if (! is_string($incoming)) {
                continue;
            }

            $trimmed = trim($incoming);
            if (! $this->isMaskedSecretPlaceholder($trimmed)) {
                continue;
            }

            $existing = Setting::get(self::AI_GROUP.'_'.$secretKey);
            if ($existing === null) {
                unset($settings[$secretKey]);
                continue;
            }

            $settings[$secretKey] = $existing;
        }

        return $settings;
    }

    private function isMaskedSecretPlaceholder(string $value): bool
    {
        return $value !== '' && preg_match('/^\*{8,}$/', $value) === 1;
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function validateLocalizationPayload(array $settings): void
    {
        if (! array_key_exists('timezone', $settings)) {
            return;
        }

        $timezone = trim((string) ($settings['timezone'] ?? ''));
        if ($timezone === '') {
            return;
        }

        if (! in_array($timezone, timezone_identifiers_list(), true)) {
            throw ValidationException::withMessages([
                'settings.timezone' => ['Invalid timezone identifier.'],
            ]);
        }
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function validateGeneralPayload(array $settings): void
    {
        $validator = Validator::make($settings, [
            'first_name' => ['sometimes', 'required', 'string', 'min:2', 'max:50', 'regex:/^[A-Za-z][A-Za-z\s\'.-]{1,49}$/'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^[A-Za-z][A-Za-z\s\'.-]{1,49}$/'],
            'email' => ['sometimes', 'required', 'string', 'email:rfc', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\+?(?=(?:\D*\d){8,15}\D*$)[0-9\s\-()]+$/'],
            'address' => ['sometimes', 'nullable', 'string', 'max:180', 'regex:/^[A-Za-z0-9\s.,\'\/-]{3,180}$/'],
            'city' => ['sometimes', 'nullable', 'string', 'max:60', 'regex:/^[A-Za-z][A-Za-z\s\'.-]{1,59}$/'],
            'state' => ['sometimes', 'nullable', 'string', 'max:60', 'regex:/^[A-Za-z][A-Za-z\s\'.-]{1,59}$/'],
            'country' => ['sometimes', 'nullable', 'string', 'max:60', 'regex:/^[A-Za-z][A-Za-z\s\'.-]{1,59}$/'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:10', 'regex:/^[A-Za-z0-9][A-Za-z0-9\s-]{2,9}$/'],
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }
    }
}

