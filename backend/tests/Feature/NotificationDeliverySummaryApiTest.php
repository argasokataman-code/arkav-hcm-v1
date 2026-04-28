<?php

namespace Tests\Feature;

use App\Models\NotificationDelivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationDeliverySummaryApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{token:string,companyId:int,user:User}
     */
    private function authContext(string $email): array
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Notification Observability',
            'email' => $email,
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => $email,
            'password' => 'StrongPass1',
        ])->assertOk();

        $token = (string) $login->json('data.accessToken');
        $user = User::query()->where('email', $email)->firstOrFail();

        $me = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/v1/identity/auth/me')
            ->assertOk();

        return [
            'token' => $token,
            'companyId' => (int) $me->json('data.activeCompany.id'),
            'user' => $user,
        ];
    }

    public function test_global_admin_can_read_notification_delivery_summary(): void
    {
        $ctx = $this->authContext('qa.login@example.com');

        NotificationDelivery::query()->create([
            'event_key' => 'billing.invoice.email_sent',
            'channel' => 'mail',
            'status' => 'sent',
            'recipient' => 'finance@example.com',
            'company_uuid' => null,
            'attempt_count' => 1,
            'metadata' => ['source' => 'test'],
            'sent_at' => now(),
            'created_at' => now()->subHour(),
        ]);

        NotificationDelivery::query()->create([
            'event_key' => 'billing.invoice.email_failed',
            'channel' => 'mail',
            'status' => 'failed',
            'recipient' => 'finance@example.com',
            'company_uuid' => null,
            'attempt_count' => 1,
            'last_error' => 'SMTP timeout',
            'metadata' => ['source' => 'test'],
            'failed_at' => now(),
            'created_at' => now()->subMinutes(30),
        ]);

        NotificationDelivery::query()->create([
            'event_key' => 'billing.invoice.reminder_failed',
            'channel' => 'mail',
            'status' => 'dropped',
            'recipient' => null,
            'company_uuid' => null,
            'attempt_count' => 1,
            'last_error' => 'Owner email not configured.',
            'metadata' => ['source' => 'test'],
            'created_at' => now()->subMinutes(10),
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$ctx['token'],
            'X-Company-Id' => (string) $ctx['companyId'],
        ])->getJson('/v1/hcm/notifications/delivery-summary?hours=24&channel=mail')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.totals.all', 3)
            ->assertJsonPath('data.totals.sent', 1)
            ->assertJsonPath('data.totals.failed', 1)
            ->assertJsonPath('data.totals.dropped', 1)
            ->assertJsonPath('data.topFailedEvents.0.eventKey', 'billing.invoice.email_failed')
            ->assertJsonPath('data.breakdown.byChannel.0.channel', 'mail');
    }

    public function test_non_global_admin_cannot_read_delivery_summary(): void
    {
        $ctx = $this->authContext('notification-observer@example.com');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$ctx['token'],
            'X-Company-Id' => (string) $ctx['companyId'],
        ])->getJson('/v1/hcm/notifications/delivery-summary')
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'ADMIN_REQUIRED');
    }

    public function test_non_global_admin_cannot_access_delivery_details_export_retry_and_templates(): void
    {
        $ctx = $this->authContext('notification-nonplain@example.com');

        $headers = [
            'Authorization' => 'Bearer '.$ctx['token'],
            'X-Company-Id' => (string) $ctx['companyId'],
        ];

        $this->withHeaders($headers)
            ->getJson('/v1/hcm/notifications/delivery-details')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'ADMIN_REQUIRED');

        $this->withHeaders($headers)
            ->getJson('/v1/hcm/notifications/delivery-export')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'ADMIN_REQUIRED');

        $this->withHeaders($headers)
            ->postJson('/v1/hcm/notifications/delivery/99999/retry')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'ADMIN_REQUIRED');

        $this->withHeaders($headers)
            ->getJson('/v1/hcm/notifications/templates')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'ADMIN_REQUIRED');
    }

}
