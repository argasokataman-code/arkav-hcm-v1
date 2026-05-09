<?php

namespace Tests\Feature;

use App\Models\HcmBillingTaxPolicy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlatformTaxSummaryApiTest extends TestCase
{
    use RefreshDatabase;

    private function elevateToGlobalAdmin(string $email): void
    {
        $user = User::query()->where('email', $email)->firstOrFail();
        $user->is_super_admin = true;
        $user->save();
    }

    public function test_pph_badan_export_returns_xlsx_by_default_for_global_admin(): void
    {
        $admin = $this->createHcmAdminWithCompany(['email' => 'platform-tax-export@example.com']);
        $this->elevateToGlobalAdmin('platform-tax-export@example.com');

        $headers = $this->withCompanyContext([
            'Authorization' => 'Bearer '.$admin['token'],
        ], $admin['company_id']);

        $response = $this->withHeaders($headers)
            ->get('/v1/saas/tax/spt-pph-badan/export?year=2026');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('.xlsx', (string) $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('PK', substr($response->streamedContent(), 0, 2));
    }

    public function test_pph_badan_export_rejects_csv_format(): void
    {
        $admin = $this->createHcmAdminWithCompany(['email' => 'platform-tax-export-csv@example.com']);
        $this->elevateToGlobalAdmin('platform-tax-export-csv@example.com');

        $headers = $this->withCompanyContext([
            'Authorization' => 'Bearer '.$admin['token'],
        ], $admin['company_id']);

        $this->withHeaders($headers)
            ->getJson('/v1/saas/tax/spt-pph-badan/export?year=2026&format=csv')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['format']);
    }

    public function test_active_ppn_rate_returns_default_when_no_active_compliance_policy_exists(): void
    {
        $admin = $this->createHcmAdminWithCompany(['email' => 'platform-tax-rate-default@example.com']);
        $this->elevateToGlobalAdmin('platform-tax-rate-default@example.com');

        $headers = $this->withCompanyContext([
            'Authorization' => 'Bearer '.$admin['token'],
        ], $admin['company_id']);

        $this->withHeaders($headers)
            ->getJson('/v1/saas/tax/active-ppn-rate')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.ppn_rate', 11)
            ->assertJsonPath('data.source', 'default');
    }

    public function test_active_ppn_rate_prefers_active_compliance_policy_setting(): void
    {
        $admin = $this->createHcmAdminWithCompany(['email' => 'platform-tax-rate-policy@example.com']);
        $this->elevateToGlobalAdmin('platform-tax-rate-policy@example.com');
        $adminUser = User::query()->where('email', 'platform-tax-rate-policy@example.com')->firstOrFail();

        HcmBillingTaxPolicy::query()->create([
            'id' => (string) Str::uuid(),
            'company_id' => $admin['company_id'],
            'billing_month' => '2026-05',
            'billing_cycle_type' => 'monthly',
            'tax_rate_percentage' => 22.00,
            'base_calculation_method' => 'invoice_amount_due',
            'effective_from' => '2026-05-01',
            'status' => 'active',
            'notes' => json_encode([
                'source' => 'government_tax_compliance_policy',
                'transaction_tax' => [
                    'tax_rate' => 12,
                ],
            ]),
            'created_by_user_id' => $adminUser->id,
            'updated_by_user_id' => $adminUser->id,
        ]);

        $headers = $this->withCompanyContext([
            'Authorization' => 'Bearer '.$admin['token'],
        ], $admin['company_id']);

        $this->withHeaders($headers)
            ->getJson('/v1/saas/tax/active-ppn-rate')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.ppn_rate', 12)
            ->assertJsonPath('data.source', 'compliance_settings');
    }
}
