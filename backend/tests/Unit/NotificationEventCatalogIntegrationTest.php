<?php

namespace Tests\Unit;

use App\Support\Hcm\NotificationEventCatalog;
use Tests\TestCase;

class NotificationEventCatalogIntegrationTest extends TestCase
{
    public function test_catalog_contains_cross_domain_phase_three_events(): void
    {
        $catalog = NotificationEventCatalog::all();

        $requiredKeys = [
            // Leave domain
            'leave.requested',
            'leave.approved',
            // Payroll domain
            'payroll.thr.batch_generated',
            'payroll.monthly.disbursed',
            // Ticketing domain
            'ticket.created',
            'ticket.closed',
            // Performance domain
            'performance.review.created',
            'performance.review.finalized',
        ];

        foreach ($requiredKeys as $eventKey) {
            $this->assertArrayHasKey($eventKey, $catalog, 'Missing event key: '.$eventKey);

            $definition = NotificationEventCatalog::definition($eventKey);
            $this->assertNotEmpty($definition['title'] ?? null, 'Missing title for: '.$eventKey);
            $this->assertNotEmpty($definition['description'] ?? null, 'Missing description for: '.$eventKey);
            $this->assertContains($definition['severity'] ?? '', ['critical', 'important', 'informational'], 'Invalid severity for: '.$eventKey);
        }
    }
}
