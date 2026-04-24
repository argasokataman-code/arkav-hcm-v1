<?php

namespace App\Support\Hcm;

use Carbon\CarbonInterface;

class NotificationPayloadFactory
{
    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $legacy
     * @return array<string, mixed>
     */
    public static function make(string $eventKey, array $meta = [], array $legacy = []): array
    {
        $definition = NotificationEventCatalog::definition($eventKey);
        $severity = (string) ($meta['severity'] ?? $definition['severity'] ?? 'informational');

        $occurredAt = $meta['occurredAt'] ?? now();
        if ($occurredAt instanceof CarbonInterface) {
            $occurredAt = $occurredAt->toIso8601String();
        }

        return array_merge([
            'eventKey' => $eventKey,
            'severity' => $severity,
            'title' => (string) ($meta['title'] ?? $definition['title'] ?? $eventKey),
            'message' => (string) ($meta['message'] ?? ''),
            'companyUuid' => $meta['companyUuid'] ?? null,
            'entityType' => (string) ($meta['entityType'] ?? ''),
            'entityUuid' => $meta['entityUuid'] ?? null,
            'actorUserUuid' => $meta['actorUserUuid'] ?? null,
            'occurredAt' => $occurredAt,
            'schemaVersion' => 1,
        ], $legacy);
    }
}
