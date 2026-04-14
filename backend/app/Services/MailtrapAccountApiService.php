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
    private function config(): array
    {
        $apiToken = trim((string) config('services.mailtrap.api_token', ''));
        $accountId = (int) config('services.mailtrap.account_id');
        $baseUrl = rtrim((string) config('services.mailtrap.base_url', 'https://mailtrap.io/api'), '/');
        $timeout = (int) config('services.mailtrap.timeout', 10);

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
}
