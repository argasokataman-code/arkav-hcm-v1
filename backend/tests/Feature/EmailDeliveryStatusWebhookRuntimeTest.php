<?php

namespace Tests\Feature;

use App\Models\NotificationDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailDeliveryStatusWebhookRuntimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_delivery_status_webhook_rejects_invalid_token(): void
    {
        config()->set('services.email_delivery.webhook_token', 'expected-token');

        $this->postJson('/webhooks/email-delivery-status', [
            'delivery_uuid' => 'delivery-123',
            'event' => 'delivered',
        ], [
            'X-Email-Delivery-Token' => 'wrong-token',
        ])
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'INVALID_WEBHOOK_TOKEN');
    }

    public function test_delivery_status_webhook_updates_delivery_to_delivered(): void
    {
        config()->set('services.email_delivery.webhook_token', 'expected-token');
        $delivery = NotificationDelivery::query()->create([
            'event_key' => 'email.compose.sent',
            'channel' => 'mail',
            'status' => 'sent',
            'notification_uuid' => 'delivery-123',
            'recipient' => 'recipient@example.com',
            'attempt_count' => 1,
            'metadata' => [],
            'sent_at' => now(),
        ]);

        $this->postJson('/webhooks/email-delivery-status', [
            'delivery_uuid' => 'delivery-123',
            'event' => 'delivered',
            'message_id' => 'provider-abc',
        ], [
            'X-Email-Delivery-Token' => 'expected-token',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'delivered');

        $delivery->refresh();
        $this->assertSame('delivered', $delivery->status);
        $this->assertNull($delivery->last_error);
        $this->assertSame('delivered', $delivery->metadata['providerStatusWebhook']['mappedStatus'] ?? null);
        $this->assertSame('provider-abc', $delivery->metadata['providerStatusWebhook']['providerMessageId'] ?? null);
    }

    public function test_delivery_status_webhook_marks_bounce_as_dropped(): void
    {
        config()->set('services.email_delivery.webhook_token', 'expected-token');
        $delivery = NotificationDelivery::query()->create([
            'event_key' => 'email.compose.sent',
            'channel' => 'mail',
            'status' => 'sent',
            'notification_uuid' => 'delivery-bounce-1',
            'recipient' => 'recipient@example.com',
            'attempt_count' => 1,
            'metadata' => [],
            'sent_at' => now(),
        ]);

        $this->postJson('/webhooks/email-delivery-status', [
            'delivery_uuid' => 'delivery-bounce-1',
            'event' => 'bounce',
            'reason' => 'Mailbox unavailable',
        ], [
            'X-Email-Delivery-Token' => 'expected-token',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'dropped');

        $delivery->refresh();
        $this->assertSame('dropped', $delivery->status);
        $this->assertSame('Mailbox unavailable', $delivery->last_error);
        $this->assertNotNull($delivery->failed_at);
    }
}
