<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Schema;
use Throwable;

class CronjobSettings
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function definitions(): array
    {
        return [
            'payment_reminder' => [
                'label' => 'Send Payment Reminder',
                'description' => 'Dispatch SendPaymentReminder job.',
                'scheduleType' => 'daily',
                'defaults' => [
                    'enabled' => true,
                    'time' => '08:00',
                    'timezone' => 'Asia/Jakarta',
                ],
            ],
            'wilayah_sync' => [
                'label' => 'Wilayah Sync',
                'description' => 'Sync wilayah.id master data to local DB.',
                'scheduleType' => 'monthly',
                'defaults' => [
                    'enabled' => true,
                    'dayOfMonth' => 1,
                    'time' => '01:00',
                    'timezone' => 'Asia/Jakarta',
                ],
            ],
            'payroll_refresh_open_period' => [
                'label' => 'Payroll Refresh Open Period',
                'description' => 'Refresh monthly payroll draft for active open period.',
                'scheduleType' => 'daily',
                'defaults' => [
                    'enabled' => true,
                    'time' => '00:00',
                    'timezone' => 'Asia/Jakarta',
                ],
            ],
            'leave_monthly_accrual' => [
                'label' => 'Leave Monthly Accrual',
                'description' => 'Post monthly earned-leave accrual.',
                'scheduleType' => 'daily',
                'defaults' => [
                    'enabled' => true,
                    'time' => '00:10',
                    'timezone' => 'Asia/Jakarta',
                ],
            ],
            'leave_yearly_carry' => [
                'label' => 'Leave Yearly Carry',
                'description' => 'Run yearly carry-forward logic (Jan 1 window).',
                'scheduleType' => 'daily',
                'defaults' => [
                    'enabled' => true,
                    'time' => '00:15',
                    'timezone' => 'Asia/Jakarta',
                ],
            ],
            'leave_daily_expire' => [
                'label' => 'Leave Daily Expire',
                'description' => 'Expire carry-forward balances by policy cutoff.',
                'scheduleType' => 'daily',
                'defaults' => [
                    'enabled' => true,
                    'time' => '00:20',
                    'timezone' => 'Asia/Jakarta',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function get(string $key): array
    {
        $definition = self::definitions()[$key] ?? null;
        if ($definition === null) {
            return [];
        }

        $defaults = $definition['defaults'];
        $stored = self::readSetting($key);
        if (! is_array($stored)) {
            $stored = [];
        }

        return array_merge($defaults, $stored);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        $result = [];

        foreach (self::definitions() as $key => $definition) {
            $result[$key] = array_merge($definition, [
                'config' => self::get($key),
            ]);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function set(string $key, array $payload): void
    {
        if (! array_key_exists($key, self::definitions())) {
            return;
        }

        try {
            if (! Schema::hasTable('settings')) {
                return;
            }

            Setting::set('cronjob_'.$key, $payload, 'cronjob');
        } catch (Throwable) {
            // Ignore persistence failures during bootstrap / migration gaps.
        }
    }

    private static function readSetting(string $key): mixed
    {
        try {
            if (! Schema::hasTable('settings')) {
                return null;
            }

            return Setting::get('cronjob_'.$key);
        } catch (Throwable) {
            return null;
        }
    }
}
