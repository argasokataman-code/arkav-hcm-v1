<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CronjobSettingsWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_hcm_admin_can_update_cronjob_configuration_via_web_form(): void
    {
        $admin = User::factory()->create([
            'email' => 'qa.login@example.com',
        ]);

        $csrfToken = 'cronjob-admin-token';

        $this->actingAs($admin)
            ->withSession(['_token' => $csrfToken])
            ->post('/cronjob', [
                '_token' => $csrfToken,
                'jobs' => [
                    'payment_reminder' => [
                        'enabled' => '1',
                        'time' => '09:30',
                        'timezone' => 'UTC',
                    ],
                    'wilayah_sync' => [
                        'time' => '02:15',
                        'dayOfMonth' => 12,
                        'timezone' => 'Asia/Jakarta',
                    ],
                ],
            ])
            ->assertRedirect(route('cronjob'));

        $paymentReminder = Setting::get('cronjob_payment_reminder');
        $wilayahSync = Setting::get('cronjob_wilayah_sync');

        $this->assertIsArray($paymentReminder);
        $this->assertSame(true, $paymentReminder['enabled']);
        $this->assertSame('09:30', $paymentReminder['time']);
        $this->assertSame('UTC', $paymentReminder['timezone']);

        $this->assertIsArray($wilayahSync);
        $this->assertSame(false, $wilayahSync['enabled']);
        $this->assertSame('02:15', $wilayahSync['time']);
        $this->assertSame(12, $wilayahSync['dayOfMonth']);
    }

    public function test_non_hcm_admin_cannot_update_cronjob_configuration(): void
    {
        $employee = User::factory()->create([
            'email' => 'employee@example.com',
        ]);

        $csrfToken = 'cronjob-employee-token';

        $this->actingAs($employee)
            ->withSession(['_token' => $csrfToken])
            ->post('/cronjob', [
                '_token' => $csrfToken,
                'jobs' => [
                    'payment_reminder' => [
                        'enabled' => '1',
                        'time' => '10:00',
                        'timezone' => 'UTC',
                    ],
                ],
            ])
            ->assertRedirect(url('lock-screen'));

        $this->assertNull(Setting::get('cronjob_payment_reminder'));
    }
}
