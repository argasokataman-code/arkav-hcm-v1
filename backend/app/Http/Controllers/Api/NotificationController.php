<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\EnsuresHcmAdmin;
use App\Mail\AdminComposeMailable;
use App\Services\NotificationDeliveryRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class NotificationController extends Controller
{
    use EnsuresHcmAdmin;

    /**
     * Send a compose email (admin notification from dashboard)
     */
    public function sendComposeEmail(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensureGlobalHcmAdmin($request)) {
            return $forbidden;
        }

        $validator = Validator::make($request->all(), [
            'to' => ['required', 'email'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Validation failed.',
                    'details' => $validator->errors()->toArray(),
                ],
            ], 422);
        }

        $validated = $validator->validated();
        $deliveryUuid = (string) Str::uuid();

        try {
            Mail::to($validated['to'])->send(new AdminComposeMailable(
                subjectLine: $validated['subject'],
                messageBody: $validated['message'],
                senderName: (string) ($request->user()?->name ?? config('app.name', 'ARCAV')),
                deliveryUuid: $deliveryUuid,
            ));

            app(NotificationDeliveryRecorder::class)->recordSent('email.compose.sent', 'mail', [
                'notificationUuid' => $deliveryUuid,
                'recipient' => $validated['to'],
                'metadata' => [
                    'deliveryUuid' => $deliveryUuid,
                    'subject' => $validated['subject'],
                    'messagePreview' => mb_substr($validated['message'], 0, 160),
                    'senderUserId' => (int) ($request->user()?->id ?? 0),
                    'senderEmail' => (string) ($request->user()?->email ?? ''),
                    'transportAccepted' => true,
                    'mailDefaultDriver' => (string) config('mail.default', ''),
                ],
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'to' => $validated['to'],
                    'subject' => $validated['subject'],
                    'sentAt' => now()->toIso8601String(),
                ],
                'message' => 'Email berhasil dikirim ke ' . $validated['to'] . '.',
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'MAIL_ERROR',
                    'message' => 'Email gagal dikirim. Cek konfigurasi email aktif lalu coba lagi.',
                ],
            ], 500);
        }
    }
}
