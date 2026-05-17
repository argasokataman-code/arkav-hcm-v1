<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotificationDelivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailInboundWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $expectedToken = trim((string) config('services.email_inbound.webhook_token', ''));
        if ($expectedToken === '') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'WEBHOOK_NOT_CONFIGURED',
                    'message' => 'Inbound webhook token belum dikonfigurasi.',
                ],
            ], 503);
        }

        $providedToken = trim((string) ($request->header('X-Email-Inbound-Token') ?? $request->input('token', '')));
        if ($providedToken === '' || ! hash_equals($expectedToken, $providedToken)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_WEBHOOK_TOKEN',
                    'message' => 'Invalid inbound webhook token.',
                ],
            ], 401);
        }

        $payload = $request->all();
        $messageId = $this->resolveMessageId($request, $payload);

        $existing = NotificationDelivery::query()
            ->where('event_key', 'email.inbound.received')
            ->where('channel', 'mail')
            ->where('notification_uuid', $messageId)
            ->first();

        if ($existing !== null) {
            return response()->json([
                'success' => true,
                'data' => [
                    'duplicate' => true,
                    'deliveryId' => $existing->id,
                ],
            ]);
        }

        $from = $this->extractEmail($payload['from'] ?? $payload['sender'] ?? $payload['sender_email'] ?? null);
        $to = $this->extractEmail($payload['to'] ?? $payload['recipient'] ?? $payload['recipient_email'] ?? null);
        $subject = trim((string) ($payload['subject'] ?? $payload['email_subject'] ?? '(No subject)'));
        $receivedAt = $this->resolveReceivedAt($payload);
        $preview = $this->resolvePreview($payload);

        $delivery = NotificationDelivery::query()->create([
            'event_key' => 'email.inbound.received',
            'channel' => 'mail',
            'status' => 'sent',
            'notification_uuid' => $messageId,
            'recipient' => $to,
            'attempt_count' => 1,
            'sent_at' => $receivedAt,
            'metadata' => [
                'direction' => 'inbound',
                'provider' => (string) config('services.email_inbound.provider', 'mailtrap'),
                'from' => $from,
                'to' => $to,
                'subject' => $subject,
                'messagePreview' => $preview,
                'receivedAt' => optional($receivedAt)->toIso8601String(),
            ],
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'duplicate' => false,
                'deliveryId' => $delivery->id,
                'messageId' => $messageId,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolvePreview(array $payload): string
    {
        $text = trim((string) ($payload['text'] ?? $payload['text_body'] ?? $payload['body_text'] ?? ''));
        if ($text === '') {
            $html = (string) ($payload['html'] ?? $payload['html_body'] ?? $payload['body_html'] ?? '');
            $text = trim(strip_tags($html));
        }

        if ($text === '') {
            return '';
        }

        return mb_substr($text, 0, 160);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveReceivedAt(array $payload): ?\Illuminate\Support\Carbon
    {
        $raw = trim((string) ($payload['received_at'] ?? $payload['date'] ?? $payload['created_at'] ?? ''));
        if ($raw === '') {
            return now();
        }

        try {
            return now()->parse($raw);
        } catch (\Throwable) {
            return now();
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveMessageId(Request $request, array $payload): string
    {
        $headerMessageId = trim((string) $request->header('X-Message-Id', ''));
        $payloadMessageId = trim((string) ($payload['message_id'] ?? $payload['messageId'] ?? $payload['id'] ?? ''));

        $candidate = $payloadMessageId !== '' ? $payloadMessageId : $headerMessageId;
        if ($candidate !== '') {
            return mb_substr($candidate, 0, 64);
        }

        $fallbackHash = sha1($request->getContent().json_encode([
            'from' => $payload['from'] ?? null,
            'to' => $payload['to'] ?? null,
            'subject' => $payload['subject'] ?? null,
            'date' => $payload['date'] ?? null,
        ]));

        return mb_substr($fallbackHash, 0, 64);
    }

    private function extractEmail(mixed $value): ?string
    {
        if (is_array($value)) {
            $first = $value[0] ?? null;
            if (is_array($first)) {
                $first = $first['email'] ?? $first['address'] ?? null;
            }

            $value = $first;
        }

        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return null;
        }

        if (preg_match('/<([^>]+)>/', $text, $matches) === 1) {
            $text = trim((string) ($matches[1] ?? ''));
        }

        return $text !== '' ? mb_substr($text, 0, 191) : null;
    }
}