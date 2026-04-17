<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'group'];
    
    protected $casts = [
        'value' => 'string', // Keep as string for flexibility, parse JSON when needed
    ];

    private const CACHE_TTL_SECONDS = 600;

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
}

