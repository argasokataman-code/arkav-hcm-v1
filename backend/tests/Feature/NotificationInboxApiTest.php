<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationInboxApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{token:string,user:User,companyId:int,companyUuid:string}
     */
    private function authContext(string $email = 'notification-inbox@example.com'): array
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Notification Inbox',
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
            'user' => $user,
            'companyId' => (int) $me->json('data.activeCompany.id'),
            'companyUuid' => (string) $me->json('data.activeCompany.uuid'),
        ];
    }

    /**
     * @param  array<string,mixed>  $data
     */
    private function seedNotification(User $user, array $data, bool $isRead = false): string
    {
        $id = (string) Str::uuid();
        $companyUuid = (string) ($data['companyUuid'] ?? '');
        $resolvedCompanyUuid = $companyUuid !== ''
            ? (string) (Company::query()->where('uuid', $companyUuid)->value('uuid') ?? '')
            : '';

        DatabaseNotification::query()->create([
            'id' => $id,
            'type' => 'tests.notification',
            'notifiable_type' => User::class,
            'notifiable_id' => (string) $user->id,
            'user_uuid' => $user->uuid,
            'company_uuid' => $resolvedCompanyUuid !== '' ? $resolvedCompanyUuid : null,
            'data' => $data,
            'read_at' => $isRead ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    public function test_inbox_list_returns_tenant_scoped_notifications_for_regular_user(): void
    {
        $ctx = $this->authContext();

        $this->seedNotification($ctx['user'], [
            'eventKey' => 'asset.assigned',
            'title' => 'Asset Assigned',
            'message' => 'Laptop assigned to you',
            'severity' => 'informational',
            'companyUuid' => $ctx['companyUuid'],
            'channel' => 'database',
        ]);

        $this->seedNotification($ctx['user'], [
            'eventKey' => 'asset.returned',
            'title' => 'Asset Returned',
            'message' => 'Asset returned by staff',
            'severity' => 'low',
            'companyUuid' => (string) Str::uuid(),
            'channel' => 'database',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$ctx['token'],
            'X-Company-Id' => (string) $ctx['companyId'],
        ])->getJson('/v1/hcm/notifications');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.eventKey', 'asset.assigned')
            ->assertJsonPath('data.meta.unreadCount', 1);
    }

    public function test_mark_notification_as_read_updates_single_notification(): void
    {
        $ctx = $this->authContext('notification-read@example.com');

        $notificationId = $this->seedNotification($ctx['user'], [
            'eventKey' => 'billing.invoice.issued',
            'title' => 'Invoice Issued',
            'message' => 'Invoice #INV-001 has been issued',
            'severity' => 'medium',
            'companyUuid' => $ctx['companyUuid'],
            'channel' => 'database',
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$ctx['token'],
            'X-Company-Id' => (string) $ctx['companyId'],
        ])->postJson('/v1/hcm/notifications/'.$notificationId.'/read')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertNotNull(DatabaseNotification::query()->where('id', $notificationId)->value('read_at'));
    }

    public function test_mark_all_as_read_and_unread_count_endpoints_work(): void
    {
        $ctx = $this->authContext('notification-read-all@example.com');

        $this->seedNotification($ctx['user'], [
            'eventKey' => 'billing.payment_failed',
            'title' => 'Payment Failed',
            'message' => 'Payment capture failed',
            'severity' => 'high',
            'companyUuid' => $ctx['companyUuid'],
            'channel' => 'database',
        ]);

        $this->seedNotification($ctx['user'], [
            'eventKey' => 'billing.payment_received',
            'title' => 'Payment Received',
            'message' => 'Payment has been received',
            'severity' => 'low',
            'companyUuid' => $ctx['companyUuid'],
            'channel' => 'database',
        ]);

        $headers = [
            'Authorization' => 'Bearer '.$ctx['token'],
            'X-Company-Id' => (string) $ctx['companyId'],
        ];

        $this->withHeaders($headers)
            ->getJson('/v1/hcm/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unreadCount', 2);

        $this->withHeaders($headers)
            ->postJson('/v1/hcm/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('data.updated', 2);

        $this->withHeaders($headers)
            ->getJson('/v1/hcm/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unreadCount', 0);
    }
}
