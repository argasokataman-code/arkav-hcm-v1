<?php

namespace Tests\Feature;

use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationPreferenceApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{token:string,user:User,companyId:int}
     */
    private function authContext(string $email = 'notification-preference@example.com'): array
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Notification Preference',
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
        ];
    }

    public function test_put_preferences_creates_and_updates_rows(): void
    {
        $ctx = $this->authContext();
        $headers = [
            'Authorization' => 'Bearer '.$ctx['token'],
            'X-Company-Id' => (string) $ctx['companyId'],
        ];

        $this->withHeaders($headers)
            ->putJson('/v1/hcm/notification-preferences', [
                'preferences' => [
                    [
                        'eventKey' => 'asset.assigned',
                        'channel' => 'database',
                        'enabled' => true,
                        'digestMode' => 'instant',
                    ],
                    [
                        'eventKey' => 'billing.invoice.overdue',
                        'channel' => 'mail',
                        'enabled' => false,
                        'digestMode' => 'daily',
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data.items');

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $ctx['user']->id,
            'event_key' => 'asset.assigned',
            'channel' => 'database',
            'enabled' => 1,
            'digest_mode' => 'instant',
        ]);

        $this->withHeaders($headers)
            ->putJson('/v1/hcm/notification-preferences', [
                'preferences' => [
                    [
                        'eventKey' => 'asset.assigned',
                        'channel' => 'database',
                        'enabled' => false,
                        'digestMode' => 'weekly',
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $ctx['user']->id,
            'event_key' => 'asset.assigned',
            'channel' => 'database',
            'enabled' => 0,
            'digest_mode' => 'weekly',
        ]);
    }

    public function test_get_preferences_returns_only_authenticated_user_rows(): void
    {
        $ctx = $this->authContext('notification-preference-list@example.com');

        NotificationPreference::query()->create([
            'user_id' => $ctx['user']->id,
            'event_key' => 'billing.payment_failed',
            'channel' => 'mail',
            'enabled' => true,
            'digest_mode' => 'instant',
        ]);

        $other = $this->authContext('notification-preference-other@example.com');
        NotificationPreference::query()->create([
            'user_id' => $other['user']->id,
            'event_key' => 'asset.returned',
            'channel' => 'database',
            'enabled' => true,
            'digest_mode' => 'daily',
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$ctx['token'],
            'X-Company-Id' => (string) $ctx['companyId'],
        ])->getJson('/v1/hcm/notification-preferences')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.eventKey', 'billing.payment_failed');
    }
}
