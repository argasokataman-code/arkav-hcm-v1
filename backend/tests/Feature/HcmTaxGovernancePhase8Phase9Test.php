<?php

namespace Tests\Feature;

use App\Models\HcmTaxGovernancePolicy;
use App\Models\Invoice;
use App\Models\PlatformRevenueTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HcmTaxGovernancePhase8Phase9Test extends TestCase
{
    use RefreshDatabase;

    public function test_numeric_policy_reference_is_accepted_with_deprecation_headers(): void
    {
        $admin = $this->createHcmAdminWithCompany(['email' => 'tax-phase8@example.com']);

        $create = $this->withHeaders($this->withCompanyContext([
            'Authorization' => 'Bearer ' . $admin['token'],
        ], $admin['company_id']))->postJson('/v1/hcm/tax-governance/policies', [
            'policyCode' => 'PPh21-TER-LEGACY',
            'name' => 'Policy Legacy Compatibility',
            'effectiveStartDate' => '2026-01-01',
            'effectiveEndDate' => null,
            'rules' => ['scheme' => 'TER'],
            'rateSchedules' => [['bracket' => 'A', 'rate' => 5]],
        ])->assertStatus(201);

        $policyUuid = (string) $create->json('data.uuid');
        $policy = HcmTaxGovernancePolicy::query()->where('uuid', $policyUuid)->firstOrFail();

        $showByNumeric = $this->withHeaders($this->withCompanyContext([
            'Authorization' => 'Bearer ' . $admin['token'],
        ], $admin['company_id']))->getJson('/v1/hcm/tax-governance/policies/' . $policy->id);

        $showByNumeric->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.uuid', $policyUuid)
            ->assertHeader('Deprecation', 'true')
            ->assertHeader('Sunset', '2026-07-26T00:00:00Z');
    }

    public function test_numeric_policy_reference_returns_event_history_with_uuid_payload(): void
    {
        $admin = $this->createHcmAdminWithCompany(['email' => 'tax-phase8-events@example.com']);

        $create = $this->withHeaders($this->withCompanyContext([
            'Authorization' => 'Bearer ' . $admin['token'],
        ], $admin['company_id']))->postJson('/v1/hcm/tax-governance/policies', [
            'policyCode' => 'PPh21-TER-EVENTS',
            'name' => 'Policy Event History',
            'effectiveStartDate' => '2026-01-01',
            'effectiveEndDate' => null,
            'rules' => ['scheme' => 'TER'],
            'rateSchedules' => [['bracket' => 'A', 'rate' => 5]],
        ])->assertStatus(201);

        $policyUuid = (string) $create->json('data.uuid');
        $policy = HcmTaxGovernancePolicy::query()->where('uuid', $policyUuid)->firstOrFail();

        $response = $this->withHeaders($this->withCompanyContext([
            'Authorization' => 'Bearer ' . $admin['token'],
        ], $admin['company_id']))->getJson('/v1/hcm/tax-governance/policies/' . $policy->id . '/events');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.policy_uuid', $policyUuid)
            ->assertHeader('Deprecation', 'true');
    }

    public function test_platform_billing_reports_require_global_admin(): void
    {
        $tenantAdmin = $this->createHcmAdminWithCompany(['email' => 'tax-phase9-tenant@example.com']);

        $this->withHeaders($this->withCompanyContext([
            'Authorization' => 'Bearer ' . $tenantAdmin['token'],
        ], $tenantAdmin['company_id']))->getJson('/v1/hcm/tax-governance/platform-billing/reports?month=2026-05')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');

        $this->withHeaders($this->withCompanyContext([
            'Authorization' => 'Bearer ' . $tenantAdmin['token'],
        ], $tenantAdmin['company_id']))->getJson('/v1/hcm/tax-governance/platform-tax-compliance/policies?per_page=5')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');

        $this->withHeaders($this->withCompanyContext([
            'Authorization' => 'Bearer ' . $tenantAdmin['token'],
        ], $tenantAdmin['company_id']))->getJson('/v1/hcm/tax-governance/platform-tax-compliance/reports?month=2026-05')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }

    public function test_global_admin_can_manage_platform_billing_policy_and_reports(): void
    {
        $admin = $this->createHcmAdminWithCompany(['email' => 'tax-phase9-global@example.com']);
        $user = User::query()->where('email', 'tax-phase9-global@example.com')->firstOrFail();
        $user->is_super_admin = true;
        $user->save();

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

        $createPolicy = $this->withHeaders($this->withCompanyContext([
            'Authorization' => 'Bearer ' . $admin['token'],
        ], $admin['company_id']))->postJson('/v1/hcm/tax-governance/platform-billing/policies', [
            'company_id' => $admin['company_id'],
            'billing_month' => '2026-05',
            'billing_cycle_type' => 'monthly',
            'tax_rate_percentage' => 11,
            'base_calculation_method' => 'invoice_amount_due',
            'effective_from' => '2026-05-01',
            'effective_to' => null,
            'status' => 'active',
        ]);

        $createPolicy->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tax_rate_percentage', 11);

        $reportResponse = $this->withHeaders($this->withCompanyContext([
            'Authorization' => 'Bearer ' . $admin['token'],
        ], $admin['company_id']))->getJson('/v1/hcm/tax-governance/platform-billing/reports?month=2026-05');

        $reportResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.summary.tenant_count', 1)
            ->assertJsonPath('data.summary.total_tax_due', 110000);

        $invoiceResponse = $this->withHeaders($this->withCompanyContext([
            'Authorization' => 'Bearer ' . $admin['token'],
        ], $admin['company_id']))->getJson('/v1/hcm/tax-governance/platform-billing/invoices?month=2026-05');

        $invoiceResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.month', '2026-05');

        $compliancePoliciesResponse = $this->withHeaders($this->withCompanyContext([
            'Authorization' => 'Bearer ' . $admin['token'],
        ], $admin['company_id']))->getJson('/v1/hcm/tax-governance/platform-tax-compliance/policies?per_page=5');

        $compliancePoliciesResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.view_mode', 'global');

        $complianceReportResponse = $this->withHeaders($this->withCompanyContext([
            'Authorization' => 'Bearer ' . $admin['token'],
        ], $admin['company_id']))->getJson('/v1/hcm/tax-governance/platform-tax-compliance/reports?month=2026-05');

        $complianceReportResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.summary.tenant_count', 1);
    }

    public function test_platform_billing_reports_use_cleared_revenue_as_primary_taxable_base(): void
    {
        $admin = $this->createHcmAdminWithCompany(['email' => 'tax-phase9-clearing@example.com']);
        $user = User::query()->where('email', 'tax-phase9-clearing@example.com')->firstOrFail();
        $user->is_super_admin = true;
        $user->save();

        $this->withHeaders($this->withCompanyContext([
            'Authorization' => 'Bearer ' . $admin['token'],
        ], $admin['company_id']))->postJson('/v1/hcm/tax-governance/platform-billing/policies', [
            'company_id' => $admin['company_id'],
            'billing_month' => '2026-05',
            'billing_cycle_type' => 'monthly',
            'tax_rate_percentage' => 10,
            'base_calculation_method' => 'invoice_amount_due',
            'effective_from' => '2026-05-01',
            'effective_to' => null,
            'status' => 'active',
        ])->assertStatus(201);

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

        $reportResponse = $this->withHeaders($this->withCompanyContext([
            'Authorization' => 'Bearer ' . $admin['token'],
        ], $admin['company_id']))->getJson('/v1/hcm/tax-governance/platform-billing/reports?month=2026-05');

        $reportResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.summary.total_taxable_revenue_amount', 1000000)
            ->assertJsonPath('data.summary.total_cleared_revenue_amount', 1000000)
            ->assertJsonPath('data.summary.total_disputed_revenue_amount', 200000)
            ->assertJsonPath('data.summary.total_reversed_revenue_amount', 150000)
            ->assertJsonPath('data.summary.total_tax_due', 100000)
            ->assertJsonPath('data.tenants.0.taxable_revenue_amount', 1000000)
            ->assertJsonPath('data.tenants.0.tax_amount_due', 100000);
    }

    public function test_platform_billing_reports_use_invoice_fallback_for_gross_when_runtime_revenue_is_missing(): void
    {
        $admin = $this->createHcmAdminWithCompany(['email' => 'tax-phase9-fallback@example.com']);
        $user = User::query()->where('email', 'tax-phase9-fallback@example.com')->firstOrFail();
        $user->is_super_admin = true;
        $user->save();

        $this->withHeaders($this->withCompanyContext([
            'Authorization' => 'Bearer ' . $admin['token'],
        ], $admin['company_id']))->postJson('/v1/hcm/tax-governance/platform-billing/policies', [
            'company_id' => $admin['company_id'],
            'billing_month' => '2026-05',
            'billing_cycle_type' => 'monthly',
            'tax_rate_percentage' => 11,
            'base_calculation_method' => 'invoice_amount_due',
            'effective_from' => '2026-05-01',
            'effective_to' => null,
            'status' => 'active',
        ])->assertStatus(201);

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

        $reportResponse = $this->withHeaders($this->withCompanyContext([
            'Authorization' => 'Bearer ' . $admin['token'],
        ], $admin['company_id']))->getJson('/v1/hcm/tax-governance/platform-billing/reports?month=2026-05');

        $reportResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.summary.total_taxable_revenue_amount', 1000000)
            ->assertJsonPath('data.summary.total_gross_revenue', 1000000)
            ->assertJsonPath('data.summary.total_tax_due', 110000)
            ->assertJsonPath('data.summary.total_net_revenue', 890000)
            ->assertJsonPath('data.summary.effective_tax_rate', 11)
            ->assertJsonPath('data.tenants.0.taxable_revenue_amount', 1000000)
            ->assertJsonPath('data.tenants.0.gross_revenue', 1000000)
            ->assertJsonPath('data.tenants.0.tax_amount_due', 110000)
            ->assertJsonPath('data.tenants.0.net_revenue', 890000);
    }
}
