<?php

namespace Tests\Feature;

use App\Models\HcmTaxGovernanceAnomaly;
use App\Models\Invoice;
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
    }

    public function test_tenant_compliance_status_uses_only_unpaid_amount_for_outstanding(): void
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
            ->assertJsonPath('data.compliance_status.billing_tax_compliance.invoices_issued', 1)
            ->assertJsonPath('data.compliance_status.billing_tax_compliance.invoices_paid', 1)
            ->assertJsonPath('data.compliance_status.billing_tax_compliance.amount_outstanding', 0)
            ->assertJsonPath('data.compliance_status.billing_tax_compliance.payment_status', 'current');
    }
}
