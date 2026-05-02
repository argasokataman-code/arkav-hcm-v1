<?php

namespace Tests\Feature;

use App\Models\PlatformRevenueTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HcmAiChatTaxIntentTest extends TestCase
{
    use RefreshDatabase;

    private function headers(string $token, int $companyId): array
    {
        return $this->withCompanyContext([
            'Authorization' => 'Bearer ' . $token,
        ], $companyId);
    }

    private function elevateToGlobalAdmin(string $email): void
    {
        $user = User::query()->where('email', $email)->firstOrFail();
        $user->is_super_admin = true;
        $user->save();
    }

    public function test_global_admin_can_ask_monthly_government_tax_summary(): void
    {
        $email = 'ai-tax-global-' . time() . '@example.com';
        $auth = $this->createHcmAdminWithCompany(['email' => $email]);
        $this->elevateToGlobalAdmin($email);

        PlatformRevenueTransaction::query()->create([
            'company_id' => $auth['company_id'],
            'source_event_type' => 'subscription.created',
            'source_entity_type' => 'subscriptions',
            'source_entity_id' => 9001,
            'transaction_type' => PlatformRevenueTransaction::TYPE_SUBSCRIPTION,
            'amount' => 1000000,
            'tax_amount' => 110000,
            'net_amount' => 890000,
            'status' => PlatformRevenueTransaction::STATUS_POSTED,
            'clearing_status' => PlatformRevenueTransaction::CLEARING_CLEARED,
            'occurred_at' => now()->startOfMonth()->addDays(2),
            'idempotency_key' => 'ai-tax-cleared-' . uniqid(),
        ]);

        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [[
                    'message' => ['content' => 'Total pajak pemerintah bulan ini tersedia di ringkasan pajak global.'],
                ]],
            ], 200),
        ]);

        $response = $this->postJson('/v1/hcm/ai/chat', [
            'message' => 'Berapa pajak yang kita bayarkan ke pemerintah bulan ini?',
        ], $this->headers($auth['token'], $auth['company_id']));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.intent', 'saas.tax.monthly')
            ->assertJsonPath('data.allowed', true)
            ->assertJsonPath('data.sources.0.label', 'SaaS Government Tax Summary');
    }

    public function test_non_global_admin_cannot_access_monthly_government_tax_summary(): void
    {
        $auth = $this->createHcmAdminWithCompany(['email' => 'ai-tax-tenant-' . time() . '@example.com']);

        $response = $this->postJson('/v1/hcm/ai/chat', [
            'message' => 'Berapa pajak yang kita bayarkan ke pemerintah bulan ini?',
        ], $this->headers($auth['token'], $auth['company_id']));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.intent', 'saas.tax.monthly')
            ->assertJsonPath('data.allowed', false)
            ->assertJsonPath('data.reply', 'Kamu tidak memiliki akses untuk informasi ini.');
    }
}
