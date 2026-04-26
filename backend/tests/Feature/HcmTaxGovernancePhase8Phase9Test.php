<?php

namespace Tests\Feature;

use App\Models\HcmTaxGovernancePolicy;
use App\Models\Invoice;
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
    }
}
