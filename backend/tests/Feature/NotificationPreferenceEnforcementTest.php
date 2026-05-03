<?php

namespace Tests\Feature;

use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification;
use Tests\TestCase;

class NotificationPreferenceEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabled_preference_blocks_database_notification_delivery(): void
    {
        $user = User::factory()->create();

        NotificationPreference::query()->create([
            'user_id' => $user->id,
            'user_uuid' => $user->uuid,
            'event_key' => 'test.notification.disabled',
            'channel' => 'database',
            'enabled' => false,
            'digest_mode' => 'instant',
        ]);

        $user->notify(new class extends Notification {
            public function via($notifiable): array
            {
                return ['database'];
            }

            public function toArray($notifiable): array
            {
                return [
                    'eventKey' => 'test.notification.disabled',
                    'title' => 'Disabled Event',
                    'message' => 'Should not be delivered',
                ];
            }
        });

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_enabled_preference_allows_database_notification_delivery(): void
    {
        $user = User::factory()->create();

        NotificationPreference::query()->create([
            'user_id' => $user->id,
            'user_uuid' => $user->uuid,
            'event_key' => 'test.notification.enabled',
            'channel' => 'database',
            'enabled' => true,
            'digest_mode' => 'instant',
        ]);

        $user->notify(new class extends Notification {
            public function via($notifiable): array
            {
                return ['database'];
            }

            public function toArray($notifiable): array
            {
                return [
                    'eventKey' => 'test.notification.enabled',
                    'title' => 'Enabled Event',
                    'message' => 'Should be delivered',
                ];
            }
        });

        $this->assertDatabaseCount('notifications', 1);
    }
}
