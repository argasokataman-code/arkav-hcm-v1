<?php

namespace Tests\Feature;

use App\Models\NotificationDelivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailInboundWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_inbound_webhook_rejects_invalid_token(): void
    {
        config()->set('services.email_inbound.webhook_token', 'valid-token-123');

        $response = $this->postJson('/webhooks/email-inbound', [
            'message_id' => 'msg-1',
            'from' => 'sender@example.com',
            'to' => 'qa.login@example.com',
            'subject' => 'Inbound Reply',
            'text' => 'Halo, ini balasan email.',
        ], [
            'X-Email-Inbound-Token' => 'invalid-token',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'INVALID_WEBHOOK_TOKEN');

        $this->assertDatabaseCount('notification_deliveries', 0);
    }

    public function test_inbound_webhook_persists_message_and_is_idempotent(): void
    {
        config()->set('services.email_inbound.webhook_token', 'valid-token-123');

        $payload = [
            'message_id' => 'msg-2026-04-25-001',
            'from' => 'sender@example.com',
            'to' => 'qa.login@example.com',
            'subject' => 'Inbound Reply',
            'text' => 'Halo, ini balasan email dari recipient.',
            'received_at' => now()->toIso8601String(),
        ];

        $first = $this->postJson('/webhooks/email-inbound', $payload, [
            'X-Email-Inbound-Token' => 'valid-token-123',
        ]);

        $first->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.duplicate', false);

        $second = $this->postJson('/webhooks/email-inbound', $payload, [
            'X-Email-Inbound-Token' => 'valid-token-123',
        ]);

        $second->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.duplicate', true);

        $this->assertDatabaseHas('notification_deliveries', [
            'event_key' => 'email.inbound.received',
            'channel' => 'mail',
            'status' => 'sent',
            'notification_uuid' => 'msg-2026-04-25-001',
            'recipient' => 'qa.login@example.com',
        ]);

        $this->assertSame(1, NotificationDelivery::query()->where('event_key', 'email.inbound.received')->count());
    }

    public function test_email_page_renders_inbound_items_in_inbox(): void
    {
        $admin = User::factory()->create([
            'email' => 'qa.login@example.com',
        ]);

        NotificationDelivery::query()->create([
            'event_key' => 'email.inbound.received',
            'channel' => 'mail',
            'status' => 'sent',
            'notification_uuid' => 'msg-inbox-1',
            'recipient' => 'qa.login@example.com',
            'attempt_count' => 1,
            'metadata' => [
                'from' => 'customer@example.com',
                'subject' => 'Re: Runtime Sent Subject',
                'messagePreview' => 'Ini balasan dari customer.',
            ],
            'sent_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('email'));

        $response
            ->assertOk()
            ->assertSeeText('Re: Runtime Sent Subject')
            ->assertSeeText('customer@example.com')
            ->assertSeeText('Ini balasan dari customer.');
    }
}
