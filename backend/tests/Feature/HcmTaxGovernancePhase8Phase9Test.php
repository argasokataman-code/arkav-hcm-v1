<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\PlatformRevenueTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HcmTaxGovernancePhase8Phase9Test extends TestCase
{
    use RefreshDatabase;

    private function elevateToGlobalAdmin(string $email): void
    {
        $user = User::query()->where('email', $email)->firstOrFail();
        $user->is_super_admin = true;
        $user->save();
    }

    private function complianceHeaders(array $admin): array
    {
        return $this->withCompanyContext([
            'Authorization' => 'Bearer ' . $admin['token'],
        ], $admin['company_id']);
    }

    private function createCompliancePolicy(array $admin, array $overrides = []): void
    {
        $payload = array_merge([
            'subscription_tax_rate' => 11,
            'addon_markup_rate' => 0,
            'billing_cycle_type' => 'monthly',
            'billing_month' => '2026-05',
            'effective_from' => '2026-05-01',
            'status' => 'active',
            'notes' => json_encode([
                'transaction_tax' => [
                    'tax_rate' => 11,
                    'tax_name' => 'PPN',
                    'description' => 'Government tax compliance baseline',
                ],
            ]),
        ], $overrides);

        $this->withHeaders($this->complianceHeaders($admin))
            ->postJson('/v1/hcm/tax-governance/platform-tax-compliance/policies', $payload)
            ->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_platform_tax_compliance_endpoints_require_global_admin(): void
    {
        $tenantAdmin = $this->createHcmAdminWithCompany(['email' => 'tax-phase9-tenant@example.com']);

        $this->withHeaders($this->complianceHeaders($tenantAdmin))
            ->getJson('/v1/hcm/tax-governance/platform-tax-compliance/policies?per_page=5')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');

        $this->withHeaders($this->complianceHeaders($tenantAdmin))
            ->getJson('/v1/hcm/tax-governance/platform-tax-compliance/reports?month=2026-05')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }

    public function test_global_admin_can_manage_platform_tax_compliance_policy_and_reports(): void
    {
        $admin = $this->createHcmAdminWithCompany(['email' => 'tax-phase9-global@example.com']);
        $this->elevateToGlobalAdmin('tax-phase9-global@example.com');

        Invoice::query()->create([
            'company_id' => $admin['company_id'],
            'invoice_number' => 'INV-PHASE9-001',
            'purchase_transaction_id' => null,
            'issue_date' => '2026-05-10',
            'due_date' => '2026-05-30',
            'amount_due' => 1000000,
            'is_paid' => false,
            'status' => 'sent',
        ]);

        $this->createCompliancePolicy($admin);

        $policiesResponse = $this->withHeaders($this->complianceHeaders($admin))
            ->getJson('/v1/hcm/tax-governance/platform-tax-compliance/policies?billing_month=2026-05&per_page=5');

        $policiesResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.view_mode', 'global')
            ->assertJsonPath('data.items_global.0.is_current_active_rule', true);

        $reportResponse = $this->withHeaders($this->complianceHeaders($admin))
            ->getJson('/v1/hcm/tax-governance/platform-tax-compliance/reports?month=2026-05');

        $reportResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.policy_configured', true)
            ->assertJsonPath('data.summary.tenant_count', 1)
            ->assertJsonPath('data.summary_global.total_tax_due', 110000)
            ->assertJsonPath('data.summary_global.total_collected_tax_liability', 110000)
            ->assertJsonPath('data.tenants_global.0.billing_month', '2026-05')
            ->assertJsonPath('data.tenants_global.0.billing_cycle_type', 'monthly')
            ->assertJsonPath('data.tenants_global.0.next_renewal_month', '2026-06')
            ->assertJsonPath('data.tenants_compliance.0.total_tax_payable', 110000);
    }

    public function test_platform_tax_compliance_report_requires_policy_for_tax_due(): void
    {
        $admin = $this->createHcmAdminWithCompany(['email' => 'tax-phase9-compliance-source@example.com']);
        $this->elevateToGlobalAdmin('tax-phase9-compliance-source@example.com');

        Invoice::query()->create([
            'company_id' => $admin['company_id'],
            'invoice_number' => 'INV-PHASE9-COMPLIANCE-001',
            'purchase_transaction_id' => null,
            'issue_date' => '2026-05-10',
            'due_date' => '2026-05-30',
            'amount_due' => 1000000,
            'is_paid' => false,
            'status' => 'sent',
        ]);

        $withoutCompliancePolicy = $this->withHeaders($this->complianceHeaders($admin))
            ->getJson('/v1/hcm/tax-governance/platform-tax-compliance/reports?month=2026-05');

        $withoutCompliancePolicy->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.policy_configured', false)
            ->assertJsonPath('data.summary_global.total_tax_due', 0)
            ->assertJsonPath('data.summary_global.total_collected_tax_liability', 0);

        $this->createCompliancePolicy($admin);

        $withCompliancePolicy = $this->withHeaders($this->complianceHeaders($admin))
            ->getJson('/v1/hcm/tax-governance/platform-tax-compliance/reports?month=2026-05');

        $withCompliancePolicy->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.policy_configured', true)
            ->assertJsonPath('data.summary_global.total_tax_due', 110000)
            ->assertJsonPath('data.summary_global.total_collected_tax_liability', 110000);
    }

    public function test_platform_tax_compliance_report_uses_cleared_revenue_as_primary_taxable_base(): void
    {
        $admin = $this->createHcmAdminWithCompany(['email' => 'tax-phase9-clearing@example.com']);
        $this->elevateToGlobalAdmin('tax-phase9-clearing@example.com');

        $this->createCompliancePolicy($admin, [
            'subscription_tax_rate' => 10,
            'notes' => json_encode([
                'transaction_tax' => [
                    'tax_rate' => 10,
                    'tax_name' => 'PPN',
                    'description' => 'Cleared revenue baseline',
                ],
            ]),
        ]);

        PlatformRevenueTransaction::query()->create([
            'company_id' => $admin['company_id'],
            'source_event_type' => 'subscription.created',
            'source_entity_type' => 'subscriptions',
            'source_entity_id' => 9101,
            'transaction_type' => PlatformRevenueTransaction::TYPE_SUBSCRIPTION,
            'amount' => 1000000,
            'tax_amount' => 0,
            'net_amount' => 1000000,
            'status' => PlatformRevenueTransaction::STATUS_POSTED,
            'clearing_status' => PlatformRevenueTransaction::CLEARING_CLEARED,
            'occurred_at' => '2026-05-12 10:00:00',
            'idempotency_key' => 'phase9-cleared-1',
        ]);

        PlatformRevenueTransaction::query()->create([
            'company_id' => $admin['company_id'],
            'source_event_type' => 'addon.purchased',
            'source_entity_type' => 'purchase_transactions',
            'source_entity_id' => 9102,
            'transaction_type' => PlatformRevenueTransaction::TYPE_ADDON_FEATURE,
            'amount' => 200000,
            'tax_amount' => 0,
            'net_amount' => 200000,
            'status' => PlatformRevenueTransaction::STATUS_POSTED,
            'clearing_status' => PlatformRevenueTransaction::CLEARING_DISPUTED,
            'occurred_at' => '2026-05-12 10:10:00',
            'idempotency_key' => 'phase9-disputed-1',
        ]);

        PlatformRevenueTransaction::query()->create([
            'company_id' => $admin['company_id'],
            'source_event_type' => 'addon.purchased',
            'source_entity_type' => 'purchase_transactions',
            'source_entity_id' => 9103,
            'transaction_type' => PlatformRevenueTransaction::TYPE_ADDON_FEATURE,
            'amount' => 150000,
            'tax_amount' => 0,
            'net_amount' => 150000,
            'status' => PlatformRevenueTransaction::STATUS_POSTED,
            'clearing_status' => PlatformRevenueTransaction::CLEARING_REVERSED,
            'occurred_at' => '2026-05-12 10:20:00',
            'idempotency_key' => 'phase9-reversed-1',
        ]);

        Invoice::query()->create([
            'company_id' => $admin['company_id'],
            'invoice_number' => 'INV-PHASE9-CLEARING-001',
            'purchase_transaction_id' => null,
            'issue_date' => '2026-05-10',
            'due_date' => '2026-05-30',
            'amount_due' => 9999999,
            'is_paid' => false,
            'status' => 'sent',
        ]);

        $reportResponse = $this->withHeaders($this->complianceHeaders($admin))
            ->getJson('/v1/hcm/tax-governance/platform-tax-compliance/reports?month=2026-05');

        $reportResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.summary.total_taxable_revenue_amount', 1000000)
            ->assertJsonPath('data.summary.total_cleared_revenue_amount', 1000000)
            ->assertJsonPath('data.summary.total_disputed_revenue_amount', 200000)
            ->assertJsonPath('data.summary.total_reversed_revenue_amount', 150000)
            ->assertJsonPath('data.summary_global.total_tax_due', 100000)
            ->assertJsonPath('data.tenants.0.taxable_revenue_amount', 1000000)
            ->assertJsonPath('data.tenants_compliance.0.total_tax_payable', 100000);
    }

    public function test_platform_tax_compliance_report_respects_yearly_cycle_metadata(): void
    {
        $admin = $this->createHcmAdminWithCompany(['email' => 'tax-phase9-yearly-cycle@example.com']);
        $this->elevateToGlobalAdmin('tax-phase9-yearly-cycle@example.com');

        Invoice::query()->create([
            'company_id' => $admin['company_id'],
            'invoice_number' => 'INV-PHASE9-YEARLY-001',
            'purchase_transaction_id' => null,
            'issue_date' => '2026-05-12',
            'due_date' => '2026-05-31',
            'amount_due' => 1000000,
            'is_paid' => false,
            'status' => 'sent',
        ]);

        $this->createCompliancePolicy($admin, [
            'billing_cycle_type' => 'yearly',
        ]);

        $this->withHeaders($this->complianceHeaders($admin))
            ->getJson('/v1/hcm/tax-governance/platform-tax-compliance/reports?month=2026-05')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tenants_global.0.billing_cycle_type', 'yearly')
            ->assertJsonPath('data.tenants_global.0.next_renewal_month', '2027-05')
            ->assertJsonPath('data.tenants_compliance.0.billing_cycle_type', 'yearly');
    }

    public function test_platform_tax_compliance_report_uses_invoice_fallback_for_gross_when_runtime_revenue_is_missing(): void
    {
        $admin = $this->createHcmAdminWithCompany(['email' => 'tax-phase9-fallback@example.com']);
        $this->elevateToGlobalAdmin('tax-phase9-fallback@example.com');

        $this->createCompliancePolicy($admin);

        Invoice::query()->create([
            'company_id' => $admin['company_id'],
            'invoice_number' => 'INV-PHASE9-FALLBACK-001',
            'purchase_transaction_id' => null,
            'issue_date' => '2026-05-15',
            'due_date' => '2026-05-30',
            'amount_due' => 1000000,
            'is_paid' => false,
            'status' => 'sent',
        ]);

        $reportResponse = $this->withHeaders($this->complianceHeaders($admin))
            ->getJson('/v1/hcm/tax-governance/platform-tax-compliance/reports?month=2026-05');

        $reportResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.summary.total_taxable_revenue_amount', 1000000)
            ->assertJsonPath('data.summary_global.total_gross_revenue', 1000000)
            ->assertJsonPath('data.summary_global.total_tax_due', 110000)
            ->assertJsonPath('data.summary_global.total_net_revenue', 890000)
            ->assertJsonPath('data.summary_global.effective_tax_rate', 11)
            ->assertJsonPath('data.tenants.0.taxable_revenue_amount', 1000000)
            ->assertJsonPath('data.tenants_global.0.gross_revenue', 1000000)
            ->assertJsonPath('data.tenants_global.0.tax_amount_due', 110000)
            ->assertJsonPath('data.tenants_global.0.net_revenue', 890000);
    }

    public function test_platform_tax_compliance_policy_list_marks_single_current_active_rule(): void
    {
        $admin = $this->createHcmAdminWithCompany(['email' => 'tax-phase9-history@example.com']);
        $this->elevateToGlobalAdmin('tax-phase9-history@example.com');

        $this->createCompliancePolicy($admin, [
            'status' => 'active',
            'effective_from' => '2026-05-15',
        ]);

        $response = $this->withHeaders($this->complianceHeaders($admin))
            ->getJson('/v1/hcm/tax-governance/platform-tax-compliance/policies?billing_month=2026-05&per_page=10');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.items_global')
            ->assertJsonPath('data.items_global.0.billing_month', '2026-05');

        $currentActiveCount = collect($response->json('data.items_global'))
            ->where('is_current_active_rule', true)
            ->count();

        $this->assertSame(1, $currentActiveCount);

        $activeStatuses = collect($response->json('data.items_global'))
            ->where('status', 'active')
            ->count();

        $this->assertSame(1, $activeStatuses);
    }
}
