<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Api\Concerns\EnsuresHcmAdmin;
use App\Http\Controllers\Controller;
use App\Services\EmailSettingsService;
use App\Services\MailtrapAccountApiService;
use App\Services\SmtpConnectionProbeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

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

        $credentialSource = $settingsCredentials['configured'] ? 'settings' : 'env';
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
            'mode' => $credentialSource,
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
            $data['error'] = [
                'code' => 'CONNECTION_FAILED',
                'message' => 'Mailtrap connection failed.',
            ];

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

    private function settingsService(): EmailSettingsService
    {
        return app(EmailSettingsService::class);
    }
}
