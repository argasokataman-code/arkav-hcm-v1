<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\PackageFeature;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HcmAiChatSubscriptionFeaturesIntentTest extends TestCase
{
    use RefreshDatabase;

    private function headers(string $token, int $companyId): array
    {
        return $this->withCompanyContext([
            'Authorization' => 'Bearer '.$token,
        ], $companyId);
    }

    public function test_current_subscription_features_are_loaded_from_runtime_package_state(): void
    {
        $auth = $this->createHcmAdminWithCompany([
            'email' => 'ai-subscription-features-'.time().'@example.com',
        ]);

        $package = Package::factory()->create([
            'code' => 'enterprise',
            'name' => 'Enterprise',
        ]);

        PackageFeature::query()->create([
            'package_uuid' => $package->uuid,
            'feature_code' => 'employee_management',
            'feature_name' => 'Employee Directory',
            'limit' => null,
        ]);

        PackageFeature::query()->create([
            'package_uuid' => $package->uuid,
            'feature_code' => 'attendance',
            'feature_name' => 'Attendance Dashboard',
            'limit' => 250,
        ]);

        PackageFeature::query()->create([
            'package_uuid' => $package->uuid,
            'feature_code' => 'legacy_hidden_module',
            'feature_name' => 'Legacy Hidden Module',
            'limit' => 0,
        ]);

        Subscription::query()->create([
            'company_id' => $auth['company_id'],
            'package_uuid' => $package->uuid,
            'plan_code' => $package->code,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'auto_renew' => true,
            'billing_cycle' => 'monthly',
            'amount' => 500000,
        ]);

        $capturedContext = '';
        Http::fake([
            '*/chat/completions' => function ($request) use (&$capturedContext) {
                $payload = $request->data();
                $messages = is_array($payload['messages'] ?? null) ? $payload['messages'] : [];
                $lastMessage = end($messages);
                $capturedContext = is_array($lastMessage) ? (string) ($lastMessage['content'] ?? '') : '';

                return Http::response([
                    'choices' => [[
                        'message' => [
                            'content' => 'Paket aktif Anda adalah Enterprise dengan fitur runtime terbaru.',
                        ],
                    ]],
                ], 200);
            },
        ]);

        $response = $this->postJson('/v1/hcm/ai/chat', [
            'message' => 'saya berlangganan paket saat ini fiturnya apa aja?',
        ], $this->headers($auth['token'], $auth['company_id']));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.intent', 'subscription.features.current')
            ->assertJsonPath('data.allowed', true)
            ->assertJsonPath('data.sources.0.label', 'Current Subscription Features');

        $this->assertStringContainsString('"package_name": "Enterprise"', $capturedContext);
        $this->assertStringContainsString('"feature_count": 2', $capturedContext);
        $this->assertStringContainsString('"employee_management"', $capturedContext);
        $this->assertStringContainsString('"attendance"', $capturedContext);
        $this->assertStringNotContainsString('"legacy_hidden_module"', $capturedContext);
    }
}
