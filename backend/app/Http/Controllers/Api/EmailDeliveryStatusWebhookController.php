<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotificationDelivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EmailDeliveryStatusWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $expectedToken = trim((string) config('services.email_delivery.webhook_token', ''));
        if ($expectedToken === '') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'WEBHOOK_NOT_CONFIGURED',
                    'message' => 'Delivery status webhook token belum dikonfigurasi.',
                ],
            ], 503);
        }

        $providedToken = trim((string) ($request->header('X-Email-Delivery-Token') ?? $request->input('token', '')));
        if ($providedToken === '' || ! hash_equals($expectedToken, $providedToken)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_WEBHOOK_TOKEN',
                    'message' => 'Invalid delivery status webhook token.',
                ],
            ], 401);
        }

        $payload = $request->all();
        $deliveryUuid = $this->resolveDeliveryUuid($request, $payload);
        if ($deliveryUuid === null) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'DELIVERY_UUID_REQUIRED',
                    'message' => 'delivery uuid tidak ditemukan di payload webhook.',
                ],
            ], 422);
        }

        $event = $this->resolveProviderEvent($payload);
        $mappedStatus = $this->mapProviderEventToStatus($event);

        $delivery = NotificationDelivery::query()
            ->where('event_key', 'email.compose.sent')
            ->where('channel', 'mail')
            ->where('notification_uuid', $deliveryUuid)
            ->latest('id')
            ->first();

        if ($delivery === null) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'DELIVERY_NOT_FOUND',
                    'message' => 'Delivery event untuk uuid tersebut tidak ditemukan.',
                ],
            ], 404);
        }

        $metadata = is_array($delivery->metadata) ? $delivery->metadata : [];
        $history = is_array($metadata['providerStatusHistory'] ?? null) ? $metadata['providerStatusHistory'] : [];
        $history[] = [
            'event' => $event,
            'mappedStatus' => $mappedStatus,
            'receivedAt' => now()->toIso8601String(),
            'providerMessageId' => $this->resolveProviderMessageId($payload),
            'rawStatus' => $payload['status'] ?? null,
            'rawReason' => $payload['reason'] ?? $payload['error'] ?? null,
        ];

        $metadata['providerStatusWebhook'] = [
            'provider' => (string) config('services.email_delivery.provider', 'mailtrap'),
            'event' => $event,
            'mappedStatus' => $mappedStatus,
            'receivedAt' => now()->toIso8601String(),
            'providerMessageId' => $this->resolveProviderMessageId($payload),
            'rawStatus' => $payload['status'] ?? null,
            'rawReason' => $payload['reason'] ?? $payload['error'] ?? null,
        ];
        $metadata['providerStatusHistory'] = array_slice($history, -20);
        $metadata['finalDeliveryState'] = $mappedStatus;

        $delivery->status = $mappedStatus;
        $delivery->last_error = in_array($mappedStatus, ['failed', 'dropped'], true)
            ? $this->normalizeNullableString($payload['reason'] ?? $payload['error'] ?? $event)
            : null;
        $delivery->failed_at = in_array($mappedStatus, ['failed', 'dropped'], true) ? now() : null;
        $delivery->metadata = $metadata;
        $delivery->save();

        Log::info('EMAIL_COMPOSE_PROVIDER_STATUS_UPDATE', [
            'deliveryId' => $delivery->id,
            'deliveryUuid' => $deliveryUuid,
            'event' => $event,
            'mappedStatus' => $mappedStatus,
            'providerMessageId' => $this->resolveProviderMessageId($payload),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'deliveryId' => $delivery->id,
                'deliveryUuid' => $deliveryUuid,
                'event' => $event,
                'status' => $mappedStatus,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveDeliveryUuid(Request $request, array $payload): ?string
    {
        $candidates = [
            $payload['delivery_uuid'] ?? null,
            $payload['deliveryUuid'] ?? null,
            $payload['notification_uuid'] ?? null,
            $payload['notificationUuid'] ?? null,
            $request->header('X-Arcav-Delivery-UUID'),
        ];

        foreach ($candidates as $candidate) {
            $value = $this->normalizeNullableString($candidate);
            if ($value !== null) {
                return mb_substr($value, 0, 64);
            }
        }

        $headerBag = is_array($payload['headers'] ?? null) ? $payload['headers'] : [];
        foreach (['X-Arcav-Delivery-UUID', 'x-arcav-delivery-uuid'] as $headerKey) {
            $value = $this->normalizeNullableString($headerBag[$headerKey] ?? null);
            if ($value !== null) {
                return mb_substr($value, 0, 64);
            }
        }

        $mailinCustom = $this->normalizeNullableString($payload['X-Mailin-custom'] ?? $payload['x-mailin-custom'] ?? null);
        if ($mailinCustom !== null && preg_match('/arcav_delivery_uuid\s*[:=]\s*([A-Za-z0-9\-]{8,64})/', $mailinCustom, $matches) === 1) {
            return mb_substr((string) ($matches[1] ?? ''), 0, 64);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveProviderEvent(array $payload): string
    {
        $event = $this->normalizeNullableString($payload['event'] ?? $payload['type'] ?? $payload['status'] ?? null);

        return strtolower((string) ($event ?? 'unknown'));
    }

    private function mapProviderEventToStatus(string $event): string
    {
        return match ($event) {
            'delivered' => 'delivered',
            'deferred' => 'deferred',
            'opened', 'click', 'clicked', 'unique_opened', 'unique_clicked' => 'delivered',
            'hard_bounce', 'soft_bounce', 'blocked', 'invalid_email', 'spam', 'unsubscribed', 'bounce' => 'dropped',
            'error', 'rejected' => 'failed',
            default => 'sent',
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveProviderMessageId(array $payload): ?string
    {
        return $this->normalizeNullableString(
            $payload['message-id']
                ?? $payload['message_id']
                ?? $payload['messageId']
                ?? null
        );
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : mb_substr($text, 0, 65535);
    }
}
