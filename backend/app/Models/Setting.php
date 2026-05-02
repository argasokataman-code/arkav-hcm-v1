<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    use AssignsUuid;
    protected $fillable = ['key', 'value', 'group'];
    
    protected $casts = [
        'value' => 'string', // Keep as string for flexibility, parse JSON when needed
    ];

    private const CACHE_TTL_SECONDS = 600;

    /**
     * Keys whose values must never be exposed in UI output or logs.
     * `getForDisplay()` will return '***' for these.
     */
    public const SENSITIVE_KEYS = [
        'ai_api_key',
        'ai_openai_api_key',
        'openai_api_key',
        'stripe_secret_key',
        'stripe_webhook_secret',
        'payment_secret',
        'smtp_password',
        'mail_password',
    ];

    private static function cacheKey(string $key): string
    {
        return 'settings:key:'.$key;
    }

    private static function groupCacheKey(string $group): string
    {
        return 'settings:group:'.$group;
    }

    /**
     * Get a setting by key with optional group filtering
     * Returns default if database is unavailable
     */
    public static function get($key, $default = null)
    {
        try {
            $setting = Cache::remember(self::cacheKey((string) $key), self::CACHE_TTL_SECONDS, function () use ($key) {
                return self::where('key', $key)->first();
            });

            if (!$setting) {
                return $default;
            }
            
            // Try to decode as JSON
            $value = json_decode($setting->value, true);
            return $value !== null ? $value : $setting->value;
        } catch (\Throwable $e) {
            // If database is unavailable, return default value gracefully
            // Log the error for debugging but don't crash
            \Log::warning('Setting::get() failed for key: '.$key, ['error' => $e->getMessage()]);
            return $default;
        }
    }

    /**
     * Set a setting by key-value pair
     */
    public static function set($key, $value, $group = 'general')
    {
        $payload = is_array($value) ? json_encode($value) : $value;

        $setting = self::updateOrCreate(
            ['key' => $key],
            ['value' => $payload, 'group' => $group]
        );

        Cache::forget(self::cacheKey((string) $key));
        Cache::forget(self::groupCacheKey((string) $group));

        return $setting;
    }

    /**
     * Get all settings by group
     */
    public static function getByGroup($group)
    {
        return Cache::remember(self::groupCacheKey((string) $group), self::CACHE_TTL_SECONDS, function () use ($group) {
            return self::where('group', $group)
                ->get()
                ->mapWithKeys(function ($item) {
                    $value = json_decode($item->value, true);
                    return [$item->key => ($value !== null ? $value : $item->value)];
                })
                ->all();
        });
    }

    public static function forget(string $key): void
    {
        $setting = self::where('key', $key)->first();
        if ($setting) {
            Cache::forget(self::groupCacheKey((string) $setting->group));
        }
        Cache::forget(self::cacheKey($key));
        self::where('key', $key)->delete();
    }

    /**
     * Whether a setting key is classified as sensitive (API keys, passwords, secrets).
     */
    public static function isSensitive(string $key): bool
    {
        return in_array(strtolower($key), array_map('strtolower', self::SENSITIVE_KEYS), true);
    }

    /**
     * Get a setting value safe for display in admin UI.
     * Returns '***' for sensitive keys to prevent accidental exposure.
     */
    public static function getForDisplay(string $key): mixed
    {
        if (self::isSensitive($key)) {
            $raw = self::get($key);
            // Return masked placeholder only when a value actually exists
            return ($raw !== null && $raw !== '') ? '***' : null;
        }

        return self::get($key);
    }

    /**
     * Get all settings in a group, masking sensitive values for safe admin display.
     *
     * @return array<string, mixed>
     */
    public static function getByGroupForDisplay(string $group): array
    {
        $all = self::getByGroup($group);
        foreach ($all as $key => $value) {
            if (self::isSensitive($key) && $value !== null && $value !== '') {
                $all[$key] = '***';
            }
        }

        return $all;
    }
}

