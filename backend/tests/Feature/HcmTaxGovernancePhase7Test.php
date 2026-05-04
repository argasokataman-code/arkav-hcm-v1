<?php

namespace Tests\Feature;

use App\Models\CompanyUser;
use App\Models\EmployeeProfile;
use App\Models\EmployeeTaxProfile;
use App\Models\HcmTaxGovernanceAnomaly;
use App\Models\Invoice;
use App\Models\PlatformRevenueTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class HcmTaxGovernancePhase7Test extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_self_audit_export_supports_pdf_download(): void
    {
        $admin = $this->createHcmAdminWithCompany(['email' => 'tax-phase7-pdf@example.com']);

        $response = $this->withHeaders($this->withCompanyContext([
            'Authorization' => 'Bearer ' . $admin['token'],
        ], $admin['company_id']))->get('/v1/hcm/tax-governance/reports/tenant-self-audit-export?format=pdf&period_start=2026-01-01&period_end=2026-01-31');

        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename="tenant-self-audit-', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_resolve_anomaly_persists_resolution_audit_trail(): void
    {
        $admin = $this->createHcmAdminWithCompany(['email' => 'tax-phase7-resolve@example.com']);

        $anomaly = HcmTaxGovernanceAnomaly::query()->create([
            'id' => (string) Str::uuid(),
            'company_id' => $admin['company_id'],
            'anomaly_type' => HcmTaxGovernanceAnomaly::TYPE_DRIFT_DETECTED,
            'severity' => HcmTaxGovernanceAnomaly::SEVERITY_WARNING,
            'description' => 'Detected payroll tax profile drift',
            'evidence_snapshot' => ['source' => 'unit-test'],
            'detected_at' => now(),
        ]);

        $response = $this->withHeaders($this->withCompanyContext([
            'Authorization' => 'Bearer ' . $admin['token'],
        ], $admin['company_id']))->patchJson('/v1/hcm/tax-governance/governance/anomalies/' . $anomaly->id . '/resolve', [
            'resolution_note' => 'Validated and fixed in payroll profile sync.',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.anomaly_id', $anomaly->id)
            ->assertJsonPath('data.resolution_note', 'Validated and fixed in payroll profile sync.');

        $anomaly->refresh();
        $this->assertNotNull($anomaly->resolved_at);
        $this->assertIsArray($anomaly->evidence_snapshot);
        $this->assertIsArray($anomaly->evidence_snapshot['resolution_audit'] ?? null);
        $this->assertSame('Validated and fixed in payroll profile sync.', $anomaly->evidence_snapshot['resolution_audit'][0]['resolution_note'] ?? null);
    }

    public function test_tenant_compliance_status_returns_expected_sections(): void
    {
        $admin = $this->createHcmAdminWithCompany(['email' => 'tax-phase7-compliance@example.com']);

        $response = $this->withHeaders($this->withCompanyContext([
            'Authorization' => 'Bearer ' . $admin['token'],
        ], $admin['company_id']))->getJson('/v1/hcm/tax-governance/reports/tenant-compliance-status');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.company_id', $admin['company_id'])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'company_id',
                    'company_name',
                    'reporting_period',
                    'compliance_status' => [
                        'statutory_tax_compliance',
                        'billing_tax_compliance',
                        'overall_status',
                        'next_review_date',
                    ],
                    'recommended_actions',
                ],
            ]);

        $this->assertMatchesRegularExpression('/^\d{4}-Q[1-4]$/', (string) $response->json('data.reporting_period'));
    }

    public function test_tenant_compliance_status_counts_only_unpaid_invoices_in_billing_snapshot(): void
    {
        $admin = $this->createHcmAdminWithCompany(['email' => 'tax-phase7-outstanding@example.com']);

        Invoice::query()->create([
            'company_id' => $admin['company_id'],
            'invoice_number' => 'INV-PHASE7-PAID-001',
            'purchase_transaction_id' => null,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'amount_due' => 333000,
            'is_paid' => true,
            'status' => 'paid',
        ]);

        $response = $this->withHeaders($this->withCompanyContext([
            'Authorization' => 'Bearer ' . $admin['token'],
        ], $admin['company_id']))->getJson('/v1/hcm/tax-governance/reports/tenant-compliance-status');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.compliance_status.billing_tax_compliance.invoices_issued', 0)
            ->assertJsonPath('data.compliance_status.billing_tax_compliance.invoices_paid', 0)
            ->assertJsonPath('data.compliance_status.billing_tax_compliance.amount_outstanding', 0)
            ->assertJsonPath('data.compliance_status.billing_tax_compliance.payment_status', 'current');
    }

    public function test_tenant_compliance_status_returns_clearing_aware_revenue_metrics(): void
    {
        $admin = $this->createHcmAdminWithCompany(['email' => 'tax-phase7-clearing-metrics@example.com']);

        PlatformRevenueTransaction::query()->create([
            'company_id' => $admin['company_id'],
            'source_event_type' => 'subscription.created',
            'source_entity_type' => 'subscriptions',
            'source_entity_id' => 3001,
            'transaction_type' => PlatformRevenueTransaction::TYPE_SUBSCRIPTION,
            'amount' => 500000,
            'tax_amount' => 0,
            'net_amount' => 500000,
            'status' => PlatformRevenueTransaction::STATUS_POSTED,
            'clearing_status' => PlatformRevenueTransaction::CLEARING_CLEARED,
            'occurred_at' => now()->startOfMonth()->addDays(2),
            'idempotency_key' => 'phase7-clearing-metric-cleared',
        ]);

        PlatformRevenueTransaction::query()->create([
            'company_id' => $admin['company_id'],
            'source_event_type' => 'addon.purchased',
            'source_entity_type' => 'purchase_transactions',
            'source_entity_id' => 3002,
            'transaction_type' => PlatformRevenueTransaction::TYPE_ADDON_FEATURE,
            'amount' => 120000,
            'tax_amount' => 0,
            'net_amount' => 120000,
            'status' => PlatformRevenueTransaction::STATUS_POSTED,
            'clearing_status' => PlatformRevenueTransaction::CLEARING_DISPUTED,
            'occurred_at' => now()->startOfMonth()->addDays(3),
            'idempotency_key' => 'phase7-clearing-metric-disputed',
        ]);

        PlatformRevenueTransaction::query()->create([
            'company_id' => $admin['company_id'],
            'source_event_type' => 'addon.purchased',
            'source_entity_type' => 'purchase_transactions',
            'source_entity_id' => 3003,
            'transaction_type' => PlatformRevenueTransaction::TYPE_ADDON_FEATURE,
            'amount' => 80000,
            'tax_amount' => 0,
            'net_amount' => 80000,
            'status' => PlatformRevenueTransaction::STATUS_POSTED,
            'clearing_status' => PlatformRevenueTransaction::CLEARING_REVERSED,
            'occurred_at' => now()->startOfMonth()->addDays(4),
            'idempotency_key' => 'phase7-clearing-metric-reversed',
        ]);

        $response = $this->withHeaders($this->withCompanyContext([
            'Authorization' => 'Bearer ' . $admin['token'],
        ], $admin['company_id']))->getJson('/v1/hcm/tax-governance/reports/tenant-compliance-status');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.compliance_status.billing_tax_compliance.taxable_revenue_amount', 500000)
            ->assertJsonPath('data.compliance_status.billing_tax_compliance.cleared_revenue_amount', 500000)
            ->assertJsonPath('data.compliance_status.billing_tax_compliance.uncleared_revenue_amount', 0)
            ->assertJsonPath('data.compliance_status.billing_tax_compliance.disputed_revenue_amount', 120000)
            ->assertJsonPath('data.compliance_status.billing_tax_compliance.reversed_revenue_amount', 80000);
    }

    public function test_tenant_compliance_status_includes_employee_pph21_profile_quality_snapshot(): void
    {
        $admin = $this->createHcmAdminWithCompany(['email' => 'tax-phase7-employee-profile-quality@example.com']);

        $employeeComplete = User::factory()->create(['email' => 'tax-employee-complete@example.com']);
        CompanyUser::query()->create([
            'company_id' => $admin['company_id'],
            'user_id' => $employeeComplete->id,
            'role' => 'employee',
            'status' => 'active',
        ]);
        $profileComplete = EmployeeProfile::query()->create([
            'user_id' => $employeeComplete->id,
            'company_id' => $admin['company_id'],
            'employment_status' => 'active',
            'contract_type' => 'permanent',
        ]);
        EmployeeTaxProfile::query()->create([
            'employee_id' => $profileComplete->id,
            'npwp' => '123456789012345',
            'tax_status' => 'TK0',
            'ptkp_status' => 'TK0',
            'effective_date' => now()->toDateString(),
        ]);

        $employeeInvalid = User::factory()->create(['email' => 'tax-employee-invalid@example.com']);
        CompanyUser::query()->create([
            'company_id' => $admin['company_id'],
            'user_id' => $employeeInvalid->id,
            'role' => 'employee',
            'status' => 'active',
        ]);
        $profileInvalid = EmployeeProfile::query()->create([
            'user_id' => $employeeInvalid->id,
            'company_id' => $admin['company_id'],
            'employment_status' => 'active',
            'contract_type' => 'permanent',
        ]);
        EmployeeTaxProfile::query()->create([
            'employee_id' => $profileInvalid->id,
            'npwp' => 'INVALID-NPWP',
            'tax_status' => 'UNKNOWN',
            'ptkp_status' => 'UNKNOWN',
            'effective_date' => now()->toDateString(),
        ]);

        $response = $this->withHeaders($this->withCompanyContext([
            'Authorization' => 'Bearer ' . $admin['token'],
        ], $admin['company_id']))->getJson('/v1/hcm/tax-governance/reports/tenant-compliance-status');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.compliance_status.employee_pph21_compliance.active_employees', 3)
            ->assertJsonPath('data.compliance_status.employee_pph21_compliance.complete_profiles', 1)
            ->assertJsonPath('data.compliance_status.employee_pph21_compliance.invalid_npwp_format', 1)
            ->assertJsonPath('data.compliance_status.employee_pph21_compliance.missing_npwp', 1)
            ->assertJsonPath('data.compliance_status.employee_pph21_compliance.missing_ptkp_status', 2)
            ->assertJsonPath('data.compliance_status.overall_status', 'attention_required');

        $nonCompliantEmployees = collect($response->json('data.compliance_status.employee_pph21_compliance.non_compliant_employees'));
        $this->assertTrue($nonCompliantEmployees->isNotEmpty());
        $invalidEmployee = $nonCompliantEmployees->first(function ($row) {
            return str_contains((string) ($row['email'] ?? ''), 'tax-employee-invalid@example.com');
        });
        $this->assertNotNull($invalidEmployee);
        $issueCodes = collect($invalidEmployee['issues'] ?? [])->pluck('code')->all();
        $this->assertContains('npwp_invalid_format', $issueCodes);
    }
}
