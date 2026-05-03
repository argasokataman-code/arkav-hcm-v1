<?php

namespace App\Listeners;

use App\Models\NotificationPreference;
use App\Models\User;
use App\Services\NotificationDeliveryRecorder;
use Illuminate\Notifications\Events\NotificationSending;

class EnforceNotificationPreference
{
    public function handle(NotificationSending $event): bool
    {
        $notifiable = $event->notifiable;
        if (! $notifiable instanceof User) {
            return true;
        }

        $channel = $this->normalizeChannel((string) $event->channel);
        if ($channel === null) {
            return true;
        }

        $eventKey = $this->extractEventKey($event);
        if ($eventKey === null) {
            return true;
        }

        $preference = NotificationPreference::query()
            ->where('event_key', $eventKey)
            ->where('channel', $channel)
            ->where(function ($query) use ($notifiable): void {
                $query->where('user_uuid', (string) $notifiable->uuid)
                    ->orWhere(function ($legacy) use ($notifiable): void {
                        $legacy->whereNull('user_uuid')
                            ->where('user_id', (int) $notifiable->id);
                    });
            })
            ->orderByRaw('CASE WHEN user_uuid IS NULL THEN 1 ELSE 0 END ASC')
            ->orderByDesc('id')
            ->first();

        if ($preference === null || $preference->enabled) {
            return true;
        }

        app(NotificationDeliveryRecorder::class)->recordDropped($eventKey, $channel, [
            'user_id' => (int) $notifiable->id,
            'user_uuid' => (string) ($notifiable->uuid ?? ''),
            'notification_class' => get_class($event->notification),
            'reason' => 'disabled_by_user_preference',
        ]);

        return false;
    }

    private function normalizeChannel(string $channel): ?string
    {
        return match ($channel) {
            'database', 'mail', 'sms', 'webhook' => $channel,
            default => null,
        };
    }

    private function extractEventKey(NotificationSending $event): ?string
    {
        $notification = $event->notification;
        if (! method_exists($notification, 'toArray')) {
            return null;
        }

        try {
            $payload = (array) $notification->toArray($event->notifiable);
        } catch (\Throwable) {
            return null;
        }

        $eventKey = (string) ($payload['eventKey'] ?? $payload['event'] ?? '');
        if ($eventKey === '') {
            return null;
        }

        return $eventKey;
    }
}
