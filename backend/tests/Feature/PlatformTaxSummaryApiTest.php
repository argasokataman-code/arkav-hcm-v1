<?php

namespace Tests\Feature;

use App\Models\HcmBillingTaxPolicy;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlatformTaxSummaryApiTest extends TestCase
{
    use RefreshDatabase;

    private function extractXlsxXml(string $binaryContent): string
    {
        if (! class_exists(\ZipArchive::class)) {
            $this->markTestSkipped('ZipArchive extension is not available.');
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'platform-tax-xlsx-');
        file_put_contents($tmpFile, $binaryContent);

        $zip = new \ZipArchive;
        $opened = $zip->open($tmpFile);
        $this->assertTrue($opened === true, 'Failed to open generated XLSX archive.');

        $xml = '';
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = (string) $zip->getNameIndex($index);
            if (! str_starts_with($name, 'xl/')) {
                continue;
            }

            $xml .= (string) $zip->getFromIndex($index);
        }

        $zip->close();
        @unlink($tmpFile);

        return $xml;
    }

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
            ->assertStatus(422);
    }

    public function test_dashboard_export_returns_xlsx_for_global_admin(): void
    {
        $admin = $this->createHcmAdminWithCompany(['email' => 'platform-tax-dashboard-export@example.com']);
        $this->elevateToGlobalAdmin('platform-tax-dashboard-export@example.com');

        Invoice::query()->create([
            'company_id' => $admin['company_id'],
            'subscription_id' => null,
            'purchase_transaction_id' => null,
            'issue_date' => '2026-05-10',
            'due_date' => '2026-05-17',
            'amount_due' => 111000,
            'billing_tax_rate_snapshot' => 11,
            'status' => 'paid',
            'is_paid' => true,
        ]);

        Payment::query()->create([
            'company_id' => $admin['company_id'],
            'subscription_id' => null,
            'purchase_transaction_id' => null,
            'invoice_id' => null,
            'amount' => 111000,
            'currency' => 'IDR',
            'status' => 'completed',
            'payment_method' => 'bank_transfer',
            'gateway' => 'manual',
            'paid_at' => '2026-05-12 10:00:00',
            'verified_at' => '2026-05-12 10:10:00',
        ]);

        $headers = $this->withCompanyContext([
            'Authorization' => 'Bearer '.$admin['token'],
        ], $admin['company_id']);

        $response = $this->withHeaders($headers)
            ->get('/v1/saas/tax/dashboard/export?month=2026-05&ppn_rate=11&format=xlsx');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('.xlsx', (string) $response->headers->get('content-disposition'));
        $content = $response->streamedContent();
        $this->assertStringStartsWith('PK', substr($content, 0, 2));

        $xml = $this->extractXlsxXml($content);
        $this->assertStringContainsString('Total Kewajiban Pajak', $xml);
        $this->assertStringContainsString('PPN Batas Setor', $xml);
        $this->assertStringContainsString('PPh 23 Batas Lapor', $xml);
        $this->assertStringNotContainsString('PPh Final', $xml);
    }

    public function test_spt_ppn_export_returns_xlsx_for_global_admin(): void
    {
        $admin = $this->createHcmAdminWithCompany(['email' => 'platform-tax-spt-ppn-export@example.com']);
        $this->elevateToGlobalAdmin('platform-tax-spt-ppn-export@example.com');

        Invoice::query()->create([
            'company_id' => $admin['company_id'],
            'subscription_id' => null,
            'purchase_transaction_id' => null,
            'issue_date' => '2026-05-10',
            'due_date' => '2026-05-17',
            'amount_due' => 111000,
            'billing_tax_rate_snapshot' => 11,
            'status' => 'paid',
            'is_paid' => true,
        ]);

        $headers = $this->withCompanyContext([
            'Authorization' => 'Bearer '.$admin['token'],
        ], $admin['company_id']);

        $response = $this->withHeaders($headers)
            ->get('/v1/saas/tax/spt-ppn/export?month=2026-05&ppn_rate=11&format=xlsx');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('.xlsx', (string) $response->headers->get('content-disposition'));
        $content = $response->streamedContent();
        $this->assertStringStartsWith('PK', substr($content, 0, 2));

        $xml = $this->extractXlsxXml($content);
        $this->assertStringContainsString('No. Invoice', $xml);
        $this->assertStringContainsString('Rp 100.000,00', $xml);
        $this->assertStringContainsString('Rp 11.000,00', $xml);
    }

    public function test_spt_pph23_export_returns_xlsx_for_global_admin(): void
    {
        $admin = $this->createHcmAdminWithCompany(['email' => 'platform-tax-spt-pph23-export@example.com']);
        $this->elevateToGlobalAdmin('platform-tax-spt-pph23-export@example.com');

        Payment::query()->create([
            'company_id' => $admin['company_id'],
            'subscription_id' => null,
            'purchase_transaction_id' => null,
            'invoice_id' => null,
            'amount' => 50000,
            'currency' => 'IDR',
            'status' => 'completed',
            'payment_method' => 'bank_transfer',
            'gateway' => 'manual',
            'paid_at' => '2026-05-12 10:00:00',
            'verified_at' => '2026-05-12 10:10:00',
        ]);

        $headers = $this->withCompanyContext([
            'Authorization' => 'Bearer '.$admin['token'],
        ], $admin['company_id']);

        $response = $this->withHeaders($headers)
            ->get('/v1/saas/tax/spt-pph23/export?month=2026-05&format=xlsx');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('.xlsx', (string) $response->headers->get('content-disposition'));
        $content = $response->streamedContent();
        $this->assertStringStartsWith('PK', substr($content, 0, 2));

        $xml = $this->extractXlsxXml($content);
        $this->assertStringContainsString('Nama Pemotong', $xml);
        $this->assertStringContainsString('Rp 50.000,00', $xml);
        $this->assertStringContainsString('Rp 1.000,00', $xml);
    }

    public function test_dashboard_requires_global_admin(): void
    {
        $admin = $this->createHcmAdminWithCompany(['email' => 'platform-tax-non-global@example.com']);

        $headers = $this->withCompanyContext([
            'Authorization' => 'Bearer '.$admin['token'],
        ], $admin['company_id']);

        $this->withHeaders($headers)
            ->getJson('/v1/saas/tax/dashboard?month=2026-05')
            ->assertStatus(403);
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

    public function test_spt_ppn_uses_tax_inclusive_amount_due_when_snapshot_exists(): void
    {
        $admin = $this->createHcmAdminWithCompany(['email' => 'platform-tax-ppn-calc@example.com']);
        $this->elevateToGlobalAdmin('platform-tax-ppn-calc@example.com');

        Invoice::query()->create([
            'company_id' => $admin['company_id'],
            'subscription_id' => null,
            'purchase_transaction_id' => null,
            'issue_date' => '2026-05-10',
            'due_date' => '2026-05-17',
            'amount_due' => 111000,
            'billing_tax_rate_snapshot' => 11,
            'status' => 'draft',
        ]);

        $headers = $this->withCompanyContext([
            'Authorization' => 'Bearer '.$admin['token'],
        ], $admin['company_id']);

        $this->withHeaders($headers)
            ->getJson('/v1/saas/tax/spt-ppn?month=2026-05&ppn_rate=11')
            ->assertOk()
            ->assertJsonPath('data.batas_setor', '2026-06-15')
            ->assertJsonPath('data.batas_lapor', '2026-06-30')
            ->assertJsonPath('data.summary.total_penyerahan_dpp', 100000)
            ->assertJsonPath('data.summary.total_ppn_keluaran', 11000)
            ->assertJsonPath('data.detail_penyerahan.0.dpp', 100000)
            ->assertJsonPath('data.detail_penyerahan.0.ppn', 11000);
    }

    public function test_spt_ppn_excludes_zero_value_invoices_from_detail_and_summary(): void
    {
        $admin = $this->createHcmAdminWithCompany(['email' => 'platform-tax-ppn-zero@example.com']);
        $this->elevateToGlobalAdmin('platform-tax-ppn-zero@example.com');

        Invoice::query()->create([
            'company_id' => $admin['company_id'],
            'subscription_id' => null,
            'purchase_transaction_id' => null,
            'issue_date' => '2026-05-09',
            'due_date' => '2026-05-16',
            'amount_due' => 0,
            'billing_tax_rate_snapshot' => 11,
            'status' => 'paid',
            'is_paid' => true,
        ]);

        Invoice::query()->create([
            'company_id' => $admin['company_id'],
            'subscription_id' => null,
            'purchase_transaction_id' => null,
            'issue_date' => '2026-05-10',
            'due_date' => '2026-05-17',
            'amount_due' => 111000,
            'billing_tax_rate_snapshot' => 11,
            'status' => 'paid',
            'is_paid' => true,
        ]);

        $headers = $this->withCompanyContext([
            'Authorization' => 'Bearer '.$admin['token'],
        ], $admin['company_id']);

        $this->withHeaders($headers)
            ->getJson('/v1/saas/tax/spt-ppn?month=2026-05&ppn_rate=11')
            ->assertOk()
            ->assertJsonPath('data.summary.invoice_count', 1)
            ->assertJsonPath('data.summary.total_penyerahan_dpp', 100000)
            ->assertJsonPath('data.summary.total_ppn_keluaran', 11000)
            ->assertJsonCount(1, 'data.detail_penyerahan');
    }

    public function test_dashboard_pph23_uses_completed_payments_instead_of_unpaid_invoice_revenue(): void
    {
        $admin = $this->createHcmAdminWithCompany(['email' => 'platform-tax-dashboard-calc@example.com']);
        $this->elevateToGlobalAdmin('platform-tax-dashboard-calc@example.com');

        Invoice::query()->create([
            'company_id' => $admin['company_id'],
            'subscription_id' => null,
            'purchase_transaction_id' => null,
            'issue_date' => '2026-05-11',
            'due_date' => '2026-05-18',
            'amount_due' => 111000,
            'billing_tax_rate_snapshot' => 11,
            'status' => 'draft',
        ]);

        Payment::query()->create([
            'company_id' => $admin['company_id'],
            'subscription_id' => null,
            'purchase_transaction_id' => null,
            'invoice_id' => null,
            'amount' => 50000,
            'currency' => 'IDR',
            'status' => 'completed',
            'payment_method' => 'bank_transfer',
            'gateway' => 'manual',
            'paid_at' => '2026-05-12 10:00:00',
            'verified_at' => '2026-05-12 10:10:00',
        ]);

        $headers = $this->withCompanyContext([
            'Authorization' => 'Bearer '.$admin['token'],
        ], $admin['company_id']);

        $this->withHeaders($headers)
            ->getJson('/v1/saas/tax/dashboard?month=2026-05&ppn_rate=11')
            ->assertOk()
            ->assertJsonPath('data.tax_obligations.ppn.dpp', 100000)
            ->assertJsonPath('data.tax_obligations.ppn.amount', 11000)
            ->assertJsonPath('data.tax_obligations.ppn.batas_setor', '2026-06-15')
            ->assertJsonPath('data.tax_obligations.ppn.batas_lapor', '2026-06-30')
            ->assertJsonPath('data.tax_obligations.pph23.dpp', 50000)
            ->assertJsonPath('data.tax_obligations.pph23.amount', 1000)
            ->assertJsonPath('data.total_kewajiban_pajak', 12000);
    }

    public function test_pph_badan_uses_dpp_basis_and_exposes_annual_deadlines(): void
    {
        $admin = $this->createHcmAdminWithCompany(['email' => 'platform-tax-pph-badan@example.com']);
        $this->elevateToGlobalAdmin('platform-tax-pph-badan@example.com');
        $adminUser = User::query()->where('email', 'platform-tax-pph-badan@example.com')->firstOrFail();

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
                    'tax_rate' => 11,
                ],
            ]),
            'created_by_user_id' => $adminUser->id,
            'updated_by_user_id' => $adminUser->id,
        ]);

        Invoice::query()->create([
            'company_id' => $admin['company_id'],
            'subscription_id' => null,
            'purchase_transaction_id' => null,
            'issue_date' => '2026-05-10',
            'due_date' => '2026-05-17',
            'amount_due' => 111000,
            'billing_tax_rate_snapshot' => 11,
            'status' => 'paid',
            'is_paid' => true,
        ]);

        $headers = $this->withCompanyContext([
            'Authorization' => 'Bearer '.$admin['token'],
        ], $admin['company_id']);

        $response = $this->withHeaders($headers)
            ->getJson('/v1/saas/tax/spt-pph-badan?year=2026');

        $response->assertOk()
            ->assertJsonPath('data.batas_pelunasan', '2027-04-30')
            ->assertJsonPath('data.batas_lapor', '2027-04-30')
            ->assertJsonPath('data.summary.total_taxable_revenue', 100000)
            ->assertJsonPath('data.summary.total_transaction_tax_liability', 11000)
            ->assertJsonPath('data.summary.total_pph_badan_payable', 22000)
            ->assertJsonPath('data.summary.total_net_profit_estimate', 67000)
            ->assertJsonPath('data.monthly_breakdown.4.taxable_revenue', 100000)
            ->assertJsonPath('data.monthly_breakdown.4.transaction_tax_liability', 11000)
            ->assertJsonPath('data.monthly_breakdown.4.pph_badan_payable', 22000);
    }
}
