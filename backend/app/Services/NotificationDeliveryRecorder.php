<?php

namespace App\Services;

use App\Models\NotificationDelivery;

class NotificationDeliveryRecorder
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function recordSent(string $eventKey, string $channel, array $context = []): NotificationDelivery
    {
        return $this->record($eventKey, $channel, 'sent', $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function recordFailed(string $eventKey, string $channel, array $context = []): NotificationDelivery
    {
        return $this->record($eventKey, $channel, 'failed', $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function recordDropped(string $eventKey, string $channel, array $context = []): NotificationDelivery
    {
        return $this->record($eventKey, $channel, 'dropped', $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function record(string $eventKey, string $channel, string $status, array $context = []): NotificationDelivery
    {
        $now = now();

        return NotificationDelivery::query()->create([
            'event_key' => $eventKey,
            'channel' => $channel,
            'status' => $status,
            'notification_uuid' => $this->nullableString($context, 'notificationUuid'),
            'recipient' => $this->nullableString($context, 'recipient'),
            'company_uuid' => $this->nullableString($context, 'companyUuid'),
            'attempt_count' => max(1, (int) ($context['attemptCount'] ?? 1)),
            'last_error' => $this->nullableString($context, 'lastError'),
            'metadata' => isset($context['metadata']) && is_array($context['metadata']) ? $context['metadata'] : null,
            'sent_at' => $status === 'sent' ? $now : null,
            'failed_at' => $status === 'failed' ? $now : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function nullableString(array $context, string $key): ?string
    {
        if (! array_key_exists($key, $context)) {
            return null;
        }

        $value = trim((string) $context[$key]);

        return $value === '' ? null : $value;
    }
}
