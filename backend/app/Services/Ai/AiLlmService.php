<?php

namespace App\Services\Ai;

use App\Models\Setting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Provider-agnostic LLM client.
 *
 * Uses OpenAI-compatible chat completions API (Ollama, OpenAI, Azure, GitHub Models).
 * Configured entirely via environment variables — no code change needed to switch provider.
 *
 * Required .env keys:
 *   AI_PROVIDER_URL   = http://localhost:11434/v1   (Ollama default)
 *   AI_MODEL          = llama3.2                     (any model loaded in Ollama)
 *   AI_API_KEY        = ollama                       (Ollama ignores this but must not be empty)
 *
 * Optional:
 *   AI_TIMEOUT_SECONDS  = 30
 *   AI_MAX_TOKENS       = 512
 */
class AiLlmService
{
    private string $baseUrl;
    private string $model;
    private string $apiKey;
    private int $timeoutSeconds;
    private int $maxTokens;

    public function __construct()
    {
        $configuredBaseUrl = $this->settingString('ai_provider_url');
        $configuredModel = $this->settingString('ai_model');
        $configuredApiKey = $this->settingString('ai_api_key')
            ?? $this->settingString('ai_openai_api_key');
        $configuredTimeoutSeconds = $this->settingInteger('ai_timeout_seconds');
        $configuredMaxTokens = $this->settingInteger('ai_max_tokens');

        $this->baseUrl = rtrim((string) ($configuredBaseUrl ?: config('ai.provider_url', 'http://localhost:11434/v1')), '/');
        $this->model = (string) ($configuredModel ?: config('ai.model', 'llama3.2'));
        $this->apiKey = (string) ($configuredApiKey ?: config('ai.api_key', 'ollama'));
        $this->timeoutSeconds = $configuredTimeoutSeconds ?: (int) config('ai.timeout_seconds', 30);
        $this->maxTokens = $configuredMaxTokens ?: (int) config('ai.max_tokens', 512);

        if ($this->timeoutSeconds <= 0) {
            $this->timeoutSeconds = 30;
        }

        if ($this->maxTokens <= 0) {
            $this->maxTokens = 512;
        }
    }

    private function settingString(string $key): ?string
    {
        $value = Setting::get($key);

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function settingInteger(string $key): ?int
    {
        $value = Setting::get($key);

        if (is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }

    /**
     * Send a guarded prompt to the LLM.
     *
     * $systemPrompt: strict instruction injected as the "system" message.
     * $userContext:  structured data (already fetched securely) passed as "user" message.
     * $userQuestion: the original question from the user (used only for phrasing the answer).
     *
     * Returns the reply string, or throws on hard failure.
     *
     * UU PDP H3: Caller MUST check AI consent before calling this method.
     * Use: checkUserHasAiConsent($userUuid) first.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    public function chat(array $messages): string
    {
        try {
            $response = Http::withToken($this->apiKey)
                ->timeout($this->timeoutSeconds)
                ->post("{$this->baseUrl}/chat/completions", [
                    'model'       => $this->model,
                    'messages'    => $messages,
                    'max_tokens'  => $this->maxTokens,
                    'temperature' => 0.2,  // low temp → factual, less creative
                    'stream'      => false,
                ]);

            if (! $response->successful()) {
                Log::warning('AiLlmService: non-2xx response', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                throw new \RuntimeException('LLM provider returned HTTP '.$response->status());
            }

            $content = $response->json('choices.0.message.content');
            if (! is_string($content) || $content === '') {
                throw new \RuntimeException('LLM response missing content');
            }

            return trim($content);

        } catch (ConnectionException $e) {
            Log::error('AiLlmService: connection failed', ['error' => $e->getMessage()]);
            throw new \RuntimeException('LLM provider unreachable: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Check if a user has active AI Chat consent.
     * UU PDP H3: Call before invoking chat() to ensure user has consented.
     *
     * @return bool true if user has active (non-withdrawn) AI consent
     */
    public function checkUserHasAiConsent(string $userUuid): bool
    {
        $employeeProfile = \App\Models\EmployeeProfile::query()
            ->where('user_uuid', $userUuid)
            ->first();

        if (! $employeeProfile) {
            return false;
        }

        $consent = \App\Models\EmployeeAiConsent::getActiveForEmployee($employeeProfile->uuid);

        return $consent !== null && $consent->isActive();
    }


    /**
     * Build a hardened message array from system prompt + data context + user question.
     *
     * $priorTurns: optional array of prior session turns in [{role, content}] format.
     * These are injected AFTER the system prompt but BEFORE the current data context,
     * giving the LLM memory of the last few exchanges without repeating full context.
     *
     * @param  array<string, mixed>                               $dataContext
     * @param  array<int, array{role: string, content: string}>  $priorTurns
     * @return array<int, array{role: string, content: string}>
     */
    public function buildMessages(string $systemPrompt, array $dataContext, string $userQuestion, array $priorTurns = []): array
    {
        $contextJson = json_encode($dataContext, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $messages = [
            [
                'role'    => 'system',
                'content' => $systemPrompt,
            ],
        ];

        // Inject prior session turns for conversational context (max 3 user+assistant pairs)
        foreach ($priorTurns as $turn) {
            $messages[] = $turn;
        }

        $messages[] = [
            'role'    => 'user',
            'content' => "DATA CONTEXT (use ONLY this to answer):\n{$contextJson}\n\nUSER QUESTION:\n{$userQuestion}",
        ];

        return $messages;
    }
}
