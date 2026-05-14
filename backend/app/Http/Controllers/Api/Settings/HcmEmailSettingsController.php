<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Api\Concerns\EnsuresHcmAdmin;
use App\Http\Controllers\Controller;
use App\Mail\AdminComposeMailable;
use App\Services\EmailSettingsService;
use App\Services\MailtrapAccountApiService;
use App\Services\NotificationDeliveryRecorder;
use App\Services\SmtpConnectionProbeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class HcmEmailSettingsController extends Controller
{
    use EnsuresHcmAdmin;

    public function show(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensureGlobalHcmAdmin($request)) {
            return $forbidden;
        }

        return response()->json([
            'success' => true,
            'data' => $this->settingsService()->getProfile(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensureGlobalHcmAdmin($request)) {
            return $forbidden;
        }

        $result = $this->settingsService()->updateProfile($request->all(), $request->user());

        return response()->json([
            'success' => true,
            'data' => $result['data'],
            'meta' => $result['meta'],
        ]);
    }

    public function mailtrapStatus(Request $request, MailtrapAccountApiService $service): JsonResponse
    {
        if ($forbidden = $this->ensureGlobalHcmAdmin($request)) {
            return $forbidden;
        }

        $settingsCredentials = $this->settingsService()->getMailtrapCredentialsForProbe();
        $envApiToken = trim((string) config('services.mailtrap.api_token', ''));
        $envAccountId = (int) config('services.mailtrap.account_id');

        $credentialSource = $settingsCredentials['configured'] ? 'env' : 'env';
        $apiToken = $settingsCredentials['configured']
            ? (string) $settingsCredentials['apiToken']
            : $envApiToken;
        $accountId = $settingsCredentials['configured']
            ? (int) $settingsCredentials['accountId']
            : $envAccountId;

        $data = [
            'provider' => 'mailtrap',
            'accountId' => $accountId > 0 ? $accountId : null,
            'credentialSource' => $credentialSource,
            'tokenConfigured' => $apiToken !== '',
            'tokenLast4' => $apiToken !== '' ? substr($apiToken, -4) : null,
            'connected' => false,
            'visibleTokenCount' => 0,
            'visibleTokens' => [],
            'error' => null,
            'mode' => 'env',
        ];

        try {
            $probe = $service->testConnection(
                $data['tokenConfigured'] ? $apiToken : null,
                $accountId > 0 ? $accountId : null,
            );

            $data['accountId'] = $probe['accountId'] ?? $data['accountId'];
            $data['tokenConfigured'] = (bool) ($probe['tokenConfigured'] ?? $data['tokenConfigured']);
            $data['connected'] = (bool) ($probe['connected'] ?? false);
            $data['visibleTokens'] = is_array($probe['visibleTokens'] ?? null) ? $probe['visibleTokens'] : [];
            $data['visibleTokenCount'] = (int) ($probe['visibleTokenCount'] ?? count($data['visibleTokens']));
            $data['error'] = $probe['error'] ?? null;

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (RuntimeException $e) {
            $data['error'] = $e->getMessage();

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        }
    }

    public function testConnection(
        Request $request,
        SmtpConnectionProbeService $smtpProbe,
        MailtrapAccountApiService $mailtrapService,
    ): JsonResponse {
        if ($forbidden = $this->ensureGlobalHcmAdmin($request)) {
            return $forbidden;
        }

        $validator = Validator::make($request->all(), [
            'provider' => 'required|string|in:smtp,mailtrap',
            'timeout' => 'nullable|integer|min:1|max:30',
            'smtp' => 'nullable|array',
            'smtp.host' => 'nullable|string|max:255',
            'smtp.port' => 'nullable|integer|min:1|max:65535',
            'smtp.encryption' => 'nullable|string|in:tls,ssl,none',
            'smtp.username' => 'nullable|string|max:255',
            'smtp.password' => 'nullable|string|max:255',
            'mailtrap' => 'nullable|array',
            'mailtrap.accountId' => 'nullable|integer|min:1',
            'mailtrap.apiToken' => 'nullable|string|max:255',
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

        $data = $validator->validated();
        $timeout = (int) ($data['timeout'] ?? 10);

        if ($data['provider'] === 'smtp') {
            $host = trim((string) ($data['smtp']['host'] ?? ''));
            $username = trim((string) ($data['smtp']['username'] ?? ''));
            $password = trim((string) ($data['smtp']['password'] ?? ''));

            if ($host === '' || $username === '' || $password === '') {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'SMTP host, username, and password are required for test connection.',
                    ],
                ], 422);
            }

            $result = $smtpProbe->probe([
                'host' => $host,
                'port' => $data['smtp']['port'] ?? null,
                'encryption' => $data['smtp']['encryption'] ?? null,
                'username' => $username,
                'password' => $password,
                'timeout' => $timeout,
            ]);

            return response()->json([
                'success' => true,
                'data' => $result,
                'meta' => [
                    'lastTestStatus' => $this->settingsService()->persistTestConnectionSnapshot($result, $request->user()),
                ],
            ]);
        }

        $accountId = (int) ($data['mailtrap']['accountId'] ?? 0);
        $apiToken = trim((string) ($data['mailtrap']['apiToken'] ?? ''));

        if ($accountId <= 0 || $apiToken === '') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Mailtrap accountId and apiToken are required for test connection.',
                ],
            ], 422);
        }

        $result = $mailtrapService->testConnection($apiToken, $accountId, $timeout);

        return response()->json([
            'success' => true,
            'data' => $result,
            'meta' => [
                'lastTestStatus' => $this->settingsService()->persistTestConnectionSnapshot($result, $request->user()),
            ],
        ]);
    }

    public function sendCompose(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensureGlobalHcmAdmin($request)) {
            return $forbidden;
        }

        $validator = Validator::make($request->all(), [
            'to' => 'required|email',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
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

        $data = $validator->validated();
        $senderName = trim((string) ($request->user()?->name ?? ''));
        if ($senderName === '') {
            $senderName = trim((string) ($this->settingsService()->getProfile()['fromName'] ?? ''));
        }
        if ($senderName === '') {
            $senderName = (string) config('app.name', 'Arkav');
        }

        $deliveryUuid = (string) Str::uuid();

        try {
            Mail::to($data['to'])->send(new AdminComposeMailable(
                $data['subject'],
                $data['message'],
                $senderName,
                $deliveryUuid,
            ));

            $transport = $this->settingsService()->resolveRuntimeSmtpTransport();
            $delivery = app(NotificationDeliveryRecorder::class)->recordSent('email.compose.sent', 'mail', [
                'notificationUuid' => $deliveryUuid,
                'recipient' => $data['to'],
                'metadata' => [
                    'deliveryUuid' => $deliveryUuid,
                    'subject' => $data['subject'],
                    'messagePreview' => mb_substr($data['message'], 0, 160),
                    'senderUserId' => (int) ($request->user()?->id ?? 0),
                    'senderEmail' => (string) ($request->user()?->email ?? ''),
                    'transportAccepted' => true,
                    'mailDefaultDriver' => (string) config('mail.default'),
                    'transportSource' => $transport['source'] ?? null,
                    'transportHost' => $transport['host'] ?? null,
                ],
            ]);

            Log::info('EMAIL_COMPOSE_ACCEPTED_BY_TRANSPORT', [
                'deliveryId' => $delivery->id,
                'deliveryUuid' => $deliveryUuid,
                'recipient' => $data['to'],
                'subject' => $data['subject'],
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'to' => $data['to'],
                    'subject' => $data['subject'],
                    'sentAt' => now()->toIso8601String(),
                ],
            ]);
        } catch (Throwable $exception) {
            report($exception);

            $delivery = app(NotificationDeliveryRecorder::class)->recordFailed('email.compose.failed', 'mail', [
                'notificationUuid' => $deliveryUuid,
                'recipient' => $data['to'] ?? null,
                'lastError' => $exception->getMessage(),
            ]);

            Log::error('EMAIL_COMPOSE_SEND_FAILED', [
                'deliveryId' => $delivery->id,
                'deliveryUuid' => $deliveryUuid,
                'recipient' => $data['to'] ?? null,
                'subject' => $data['subject'] ?? null,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'EMAIL_SEND_FAILED',
                    'message' => 'Email gagal dikirim. Periksa konfigurasi env SMTP lalu coba lagi.',
                ],
            ], 500);
        }
    }

    private function settingsService(): EmailSettingsService
    {
        return app(EmailSettingsService::class);
    }
}
