<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class MailtrapAccountApiService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function listApiTokens(): array
    {
        $config = $this->config();

        return $this->listApiTokensForConfig($config);
    }

    /**
     * @return array<string, mixed>
     */
    public function testConnection(?string $apiToken = null, ?int $accountId = null, ?int $timeout = null): array
    {
        $providedToken = trim((string) ($apiToken ?? ''));
        $providedAccountId = (int) ($accountId ?? 0);

        $tokenConfigured = $providedToken !== '' || trim((string) config('services.mailtrap.api_token', '')) !== '';
        $effectiveAccountId = $providedAccountId > 0 ? $providedAccountId : (int) config('services.mailtrap.account_id');

        $data = [
            'provider' => 'mailtrap',
            'mode' => 'ephemeral',
            'persisted' => false,
            'accountId' => $effectiveAccountId > 0 ? $effectiveAccountId : null,
            'tokenConfigured' => $tokenConfigured,
            'connected' => false,
            'visibleTokenCount' => 0,
            'visibleTokens' => [],
            'testedAt' => now()->toIso8601String(),
            'error' => null,
        ];

        try {
            $config = $this->config([
                'api_token' => $providedToken !== '' ? $providedToken : null,
                'account_id' => $providedAccountId > 0 ? $providedAccountId : null,
                'timeout' => $timeout,
            ]);

            $tokens = $this->listApiTokensForConfig($config);

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

            return $data;
        } catch (RuntimeException $e) {
            $data['error'] = $this->normalizeProbeError($e->getMessage());

            return $data;
        }
    }

    /**
     * @param array{api_token: string, account_id: int, base_url: string, timeout: int} $config
     * @return array<int, array<string, mixed>>
     */
    private function listApiTokensForConfig(array $config): array
    {

        $response = Http::timeout($config['timeout'])
            ->acceptJson()
            ->withToken($config['api_token'])
            ->get($config['base_url'].'/accounts/'.$config['account_id'].'/api_tokens');

        if (! $response->successful()) {
            $message = $this->extractErrorMessage($response->json(), $response->body());

            throw new RuntimeException('Mailtrap API request failed ('.$response->status().'): '.$message);
        }

        $payload = $response->json();

        return is_array($payload) ? $payload : [];
    }

    /**
     * @return array{api_token: string, account_id: int, base_url: string, timeout: int}
     */
    private function config(array $overrides = []): array
    {
        $apiToken = trim((string) ($overrides['api_token'] ?? config('services.mailtrap.api_token', '')));
        $accountId = (int) ($overrides['account_id'] ?? config('services.mailtrap.account_id'));
        $baseUrl = rtrim((string) config('services.mailtrap.base_url', 'https://mailtrap.io/api'), '/');
        $timeout = (int) ($overrides['timeout'] ?? config('services.mailtrap.timeout', 10));

        if ($apiToken === '') {
            throw new RuntimeException('MAILTRAP_API_TOKEN is not configured.');
        }

        if ($accountId <= 0) {
            throw new RuntimeException('MAILTRAP_ACCOUNT_ID is not configured or invalid.');
        }

        if ($timeout <= 0) {
            $timeout = 10;
        }

        return [
            'api_token' => $apiToken,
            'account_id' => $accountId,
            'base_url' => $baseUrl,
            'timeout' => $timeout,
        ];
    }

    /**
     * @param mixed $json
     */
    private function extractErrorMessage(mixed $json, string $fallbackBody): string
    {
        if (is_array($json)) {
            $message = $json['message'] ?? $json['error'] ?? null;
            if (is_string($message) && trim($message) !== '') {
                return $message;
            }
        }

        $body = trim($fallbackBody);

        return $body !== '' ? $body : 'Unknown Mailtrap API error.';
    }

    /**
     * @return array{code: string, message: string}
     */
    private function normalizeProbeError(string $message): array
    {
        $raw = strtolower(trim($message));

        if (str_contains($raw, 'timed out')) {
            return [
                'code' => 'TIMEOUT',
                'message' => 'Mailtrap API request timed out.',
            ];
        }

        if (str_contains($raw, '401') || str_contains($raw, '403') || str_contains($raw, 'unauthorized')) {
            return [
                'code' => 'AUTH_FAILED',
                'message' => 'Mailtrap authentication failed.',
            ];
        }

        if (str_contains($raw, 'not configured')) {
            return [
                'code' => 'CONFIGURATION_ERROR',
                'message' => 'Mailtrap credentials are incomplete or invalid.',
            ];
        }

        return [
            'code' => 'CONNECTION_FAILED',
            'message' => 'Mailtrap connection failed.',
        ];
    }
}
