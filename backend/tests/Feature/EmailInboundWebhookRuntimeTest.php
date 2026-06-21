<?php

namespace Tests\Feature;

use App\Models\NotificationDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailInboundWebhookRuntimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_inbound_webhook_rejects_invalid_token(): void
    {
        config()->set('services.email_inbound.webhook_token', 'expected-token');

        $this->postJson('/webhooks/email-inbound', [
            'message_id' => 'msg-1',
        ], [
            'X-Email-Inbound-Token' => 'wrong-token',
        ])
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'INVALID_WEBHOOK_TOKEN');
    }

    public function test_inbound_webhook_persists_message_and_is_idempotent(): void
    {
        config()->set('services.email_inbound.webhook_token', 'expected-token');

        $payload = [
            'message_id' => 'msg-123',
            'from' => 'Sender <sender@example.com>',
            'to' => 'recipient@example.com',
            'subject' => 'Inbound hello',
            'text' => 'Hello from inbound webhook runtime test.',
            'received_at' => '2026-05-17T11:45:00+07:00',
        ];

        $this->postJson('/webhooks/email-inbound', $payload, [
            'X-Email-Inbound-Token' => 'expected-token',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.duplicate', false);

        $this->assertDatabaseHas('notification_deliveries', [
            'event_key' => 'email.inbound.received',
            'channel' => 'mail',
            'notification_uuid' => 'msg-123',
            'recipient' => 'recipient@example.com',
            'status' => 'sent',
        ]);

        $delivery = NotificationDelivery::query()->where('notification_uuid', 'msg-123')->firstOrFail();
        $this->assertSame('sender@example.com', $delivery->metadata['from'] ?? null);
        $this->assertSame('Inbound hello', $delivery->metadata['subject'] ?? null);

        $this->postJson('/webhooks/email-inbound', $payload, [
            'X-Email-Inbound-Token' => 'expected-token',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.duplicate', true)
            ->assertJsonPath('data.deliveryId', $delivery->id);

        $this->assertSame(1, NotificationDelivery::query()->where('notification_uuid', 'msg-123')->count());
    }
}
