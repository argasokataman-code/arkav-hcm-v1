<?php

namespace Tests\Unit;

use App\Support\Hcm\NotificationEventCatalog;
use App\Support\Hcm\NotificationPayloadFactory;
use Tests\TestCase;

class NotificationEventCatalogTest extends TestCase
{
    public function test_catalog_contains_core_phase_zero_events(): void
    {
        $events = NotificationEventCatalog::all();

        $expected = [
            'asset.assigned',
            'asset.returned',
            'subscription.change_approval_needed',
            'auth.password_reset_link_requested',
            'billing.invoice.email_sent',
            'billing.invoice.email_failed',
            'billing.invoice.reminder_sent',
            'billing.invoice.reminder_failed',
            'billing.payment_received',
            'billing.invoice.overdue',
            'billing.subscription.cancelled',
            'billing.invoice.issued',
            'billing.subscription.expiring_in_7_days',
            'billing.payment_failed',
        ];

        foreach ($expected as $eventKey) {
            $this->assertArrayHasKey($eventKey, $events, "Missing event key in catalog: {$eventKey}");
            $this->assertNotSame('', (string) ($events[$eventKey]['severity'] ?? ''));
            $this->assertNotSame('', (string) ($events[$eventKey]['title'] ?? ''));
        }
    }

    public function test_payload_factory_merges_canonical_and_legacy_fields(): void
    {
        $payload = NotificationPayloadFactory::make('asset.assigned', [
            'entityType' => 'asset',
            'entityUuid' => 'uuid-asset-1',
            'message' => 'test',
        ], [
            'event' => 'asset.assigned',
            'assetCode' => 'LT-0001',
        ]);

        $this->assertSame('asset.assigned', $payload['eventKey']);
        $this->assertSame('important', $payload['severity']);
        $this->assertSame('asset', $payload['entityType']);
        $this->assertSame('uuid-asset-1', $payload['entityUuid']);
        $this->assertSame('asset.assigned', $payload['event']);
        $this->assertSame('LT-0001', $payload['assetCode']);
    }
}
