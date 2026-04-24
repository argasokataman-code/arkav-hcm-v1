<?php

namespace Tests\Feature;

use App\Models\NotificationDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailDeliveryStatusWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_delivery_status_webhook_rejects_invalid_token(): void
    {
        config()->set('services.email_delivery.webhook_token', 'valid-token-123');

        $response = $this->postJson('/webhooks/email-delivery-status', [
            'event' => 'delivered',
            'delivery_uuid' => 'uuid-test-1',
        ], [
            'X-Email-Delivery-Token' => 'invalid-token',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'INVALID_WEBHOOK_TOKEN');
    }

    public function test_delivery_status_webhook_updates_delivery_to_delivered(): void
    {
        config()->set('services.email_delivery.webhook_token', 'valid-token-123');

        $delivery = NotificationDelivery::query()->create([
            'event_key' => 'email.compose.sent',
            'channel' => 'mail',
            'status' => 'sent',
            'notification_uuid' => 'uuid-test-2',
            'recipient' => 'argasokataman@gmail.com',
            'attempt_count' => 1,
            'metadata' => [
                'subject' => 'Initial Subject',
            ],
            'sent_at' => now(),
        ]);

        $response = $this->postJson('/webhooks/email-delivery-status', [
            'event' => 'delivered',
            'delivery_uuid' => 'uuid-test-2',
            'message-id' => '<provider-message-id-1@example.com>',
        ], [
            'X-Email-Delivery-Token' => 'valid-token-123',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.deliveryId', $delivery->id)
            ->assertJsonPath('data.status', 'delivered');

        $delivery->refresh();
        $this->assertSame('delivered', $delivery->status);
        $this->assertNull($delivery->failed_at);
        $this->assertSame('delivered', $delivery->metadata['providerStatusWebhook']['event'] ?? null);
        $this->assertSame('delivered', $delivery->metadata['finalDeliveryState'] ?? null);
    }

    public function test_delivery_status_webhook_marks_bounce_as_dropped(): void
    {
        config()->set('services.email_delivery.webhook_token', 'valid-token-123');

        NotificationDelivery::query()->create([
            'event_key' => 'email.compose.sent',
            'channel' => 'mail',
            'status' => 'sent',
            'notification_uuid' => 'uuid-test-3',
            'recipient' => 'argasokataman@gmail.com',
            'attempt_count' => 1,
            'metadata' => [
                'subject' => 'Initial Subject',
            ],
            'sent_at' => now(),
        ]);

        $response = $this->postJson('/webhooks/email-delivery-status', [
            'event' => 'hard_bounce',
            'delivery_uuid' => 'uuid-test-3',
            'reason' => 'Mailbox unavailable',
        ], [
            'X-Email-Delivery-Token' => 'valid-token-123',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'dropped');

        $this->assertDatabaseHas('notification_deliveries', [
            'event_key' => 'email.compose.sent',
            'notification_uuid' => 'uuid-test-3',
            'status' => 'dropped',
            'last_error' => 'Mailbox unavailable',
        ]);
    }
}
