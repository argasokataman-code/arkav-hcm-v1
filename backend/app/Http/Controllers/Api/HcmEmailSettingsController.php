<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MailtrapAccountApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class HcmEmailSettingsController extends Controller
{
    public function mailtrapStatus(Request $request, MailtrapAccountApiService $service): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ADMIN_REQUIRED',
                    'message' => 'Admin access required.',
                ],
            ], 403);
        }

        if (! $user->isGlobalHcmAdmin()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ADMIN_REQUIRED',
                    'message' => 'Admin access required.',
                ],
            ], 403);
        }

        $apiToken = trim((string) config('services.mailtrap.api_token', ''));
        $accountId = (int) config('services.mailtrap.account_id');

        $data = [
            'provider' => 'mailtrap',
            'accountId' => $accountId > 0 ? $accountId : null,
            'tokenConfigured' => $apiToken !== '',
            'tokenLast4' => $apiToken !== '' ? substr($apiToken, -4) : null,
            'connected' => false,
            'visibleTokenCount' => 0,
            'visibleTokens' => [],
            'error' => null,
        ];

        if (! $data['tokenConfigured'] || $accountId <= 0) {
            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Mailtrap credentials are not fully configured in environment.',
            ]);
        }

        try {
            $tokens = $service->listApiTokens();
            $visible = array_map(static function (mixed $token): array {
                if (! is_array($token)) {
                    return [];
                }

                return [
                    'id' => $token['id'] ?? null,
                    'name' => $token['name'] ?? null,
                    'last4' => $token['last_4_digits'] ?? null,
                    'expiresAt' => $token['expires_at'] ?? null,
                ];
            }, $tokens);
            $visible = array_values(array_filter($visible));

            $data['connected'] = true;
            $data['visibleTokens'] = $visible;
            $data['visibleTokenCount'] = count($visible);

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
}
