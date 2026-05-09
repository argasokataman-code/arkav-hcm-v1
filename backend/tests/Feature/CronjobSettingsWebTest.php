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
                    'saas_terminate_expired_subscriptions' => [
                        'enabled' => '1',
                        'time' => '02:30',
                        'timezone' => 'Asia/Jakarta',
                    ],
                    'saas_recurring_billing' => [
                        'enabled' => '1',
                        'time' => '06:15',
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
        $saasTerminate = Setting::get('cronjob_saas_terminate_expired_subscriptions');
        $saasRecurring = Setting::get('cronjob_saas_recurring_billing');
        $wilayahSync = Setting::get('cronjob_wilayah_sync');

        $this->assertIsArray($paymentReminder);
        $this->assertSame(true, $paymentReminder['enabled']);
        $this->assertSame('09:30', $paymentReminder['time']);
        $this->assertSame('UTC', $paymentReminder['timezone']);

        $this->assertIsArray($saasTerminate);
        $this->assertSame(true, $saasTerminate['enabled']);
        $this->assertSame('02:30', $saasTerminate['time']);
        $this->assertSame('Asia/Jakarta', $saasTerminate['timezone']);

        $this->assertIsArray($saasRecurring);
        $this->assertSame(true, $saasRecurring['enabled']);
        $this->assertSame('06:15', $saasRecurring['time']);
        $this->assertSame('UTC', $saasRecurring['timezone']);

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
            ->assertRedirect(url('employee-dashboard'));

        $this->assertNull(Setting::get('cronjob_payment_reminder'));
    }

    public function test_hcm_admin_receives_validation_errors_for_invalid_cronjob_payload(): void
    {
        $admin = User::factory()->create([
            'email' => 'qa.login@example.com',
        ]);

        $csrfToken = 'cronjob-validation-token';

        $this->actingAs($admin)
            ->withSession(['_token' => $csrfToken])
            ->from('/cronjob')
            ->post('/cronjob', [
                '_token' => $csrfToken,
                'jobs' => [
                    'payment_reminder' => [
                        'enabled' => '1',
                        'time' => '25:90',
                        'timezone' => 'Mars/Phobos',
                    ],
                    'wilayah_sync' => [
                        'time' => '02:15',
                        'timezone' => 'Asia/Jakarta',
                        'dayOfMonth' => 40,
                    ],
                ],
            ])
            ->assertRedirect('/cronjob')
            ->assertSessionHasErrors([
                'jobs.payment_reminder.time',
                'jobs.payment_reminder.timezone',
                'jobs.wilayah_sync.dayOfMonth',
            ]);

        $this->assertNull(Setting::get('cronjob_payment_reminder'));
        $this->assertNull(Setting::get('cronjob_wilayah_sync'));
    }
}
