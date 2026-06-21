<?php

namespace Tests\Unit;

use App\Models\Setting;
use App\Services\Ai\AiLlmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiLlmServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_uses_ui_settings_with_env_fallback(): void
    {
        config([
            'ai.provider_url' => 'https://env.example/v1',
            'ai.model' => 'env-model',
            'ai.api_key' => 'env-key',
            'ai.max_tokens' => 256,
            'ai.timeout_seconds' => 20,
        ]);

        Setting::set('ai_provider_url', 'https://ui.example/v1', 'ai');
        Setting::set('ai_model', 'ui-model', 'ai');
        Setting::set('ai_openai_api_key', 'ui-key', 'ai');
        Setting::set('ai_max_tokens', 333, 'ai');
        Setting::set('ai_timeout_seconds', 15, 'ai');

        Http::fake(function (ClientRequest $request) {
            $this->assertSame('Bearer ui-key', $request->header('Authorization')[0] ?? null);
            $this->assertSame('ui-model', $request->data()['model'] ?? null);
            $this->assertSame(333, $request->data()['max_tokens'] ?? null);
            $this->assertSame('https://ui.example/v1/chat/completions', (string) $request->url());

            return Http::response([
                'choices' => [
                    [
                        'message' => ['content' => 'From UI settings'],
                    ],
                ],
            ], 200);
        });

        $service = new AiLlmService;

        $reply = $service->chat([
            ['role' => 'system', 'content' => 'Answer briefly.'],
            ['role' => 'user', 'content' => 'Hello'],
        ]);

        $this->assertSame('From UI settings', $reply);
    }
}
