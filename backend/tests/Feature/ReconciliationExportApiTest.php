<?php

namespace Tests\Feature;

use App\Models\CompanyUser;
use App\Models\EmployeeProfile;
use App\Models\ExportReconciliationEvidence;
use App\Models\HcmPayrollRun;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReconciliationExportApiTest extends TestCase
{
    use RefreshDatabase;

    private function registerAndLogin(string $name, string $email, string $designation, string $team): string
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => $name,
            'email' => $email,
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $user = User::query()->where('email', $email)->firstOrFail();
        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'team' => $team,
                'designation' => $designation,
                'employment_status' => 'active',
                'base_salary' => 0,
                'fixed_allowance' => 0,
            ],
        );

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => $email,
            'password' => 'StrongPass1',
        ])->assertOk();

        return (string) $login->json('data.accessToken');
    }

    private function adminToken(): string
    {
        return $this->registerAndLogin('Recon Admin', 'qa.login@example.com', 'HR Admin', 'HR');
    }

    private function employeeToken(): string
    {
        return $this->registerAndLogin('Recon Employee', 'recon-employee@example.com', 'Staff', 'Operations');
    }

    private function activeCompanyIdForUser(string $email): int
    {
        $user = User::query()->where('email', $email)->firstOrFail();

        return (int) CompanyUser::query()
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->value('company_id');
    }

    private function createDraftRun(string $token, int $year, int $month): int
    {
        $periodId = (int) $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/v1/hcm/payroll-periods', [
                'periodYear' => $year,
                'periodMonth' => $month,
            ])
            ->assertStatus(201)
            ->json('data.id');

        return (int) $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/v1/hcm/payroll-periods/'.$periodId.'/calculate-draft')
            ->assertOk()
            ->json('data.run.id');
    }

    public function test_admin_can_store_list_and_download_reconciliation_evidence(): void
    {
        Storage::fake('local');

        $admin = $this->adminToken();
        $relativePath = 'reconciliation/company_1/payroll-run-10.csv';
        Storage::disk('local')->put($relativePath, "employee_id,amount\n1,1000000\n");

        $create = $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/reconciliation/exports', [
                'featureKey' => 'payroll_run',
                'actionKey' => 'finalize',
                'scopeRef' => '10',
                'fileFormat' => 'csv',
                'filePath' => $relativePath,
                'rowCount' => 1,
                'filterPayload' => ['periodMonth' => 4, 'periodYear' => 2026],
                'datasetChecksum' => hash('sha256', 'sample'),
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $evidenceId = (int) $create->json('data.id');

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->getJson('/v1/reconciliation/exports?featureKey=payroll_run&actionKey=finalize')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.pagination.total', 1);

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->get('/v1/reconciliation/exports/'.$evidenceId.'/download')
            ->assertOk();
    }

    public function test_admin_can_create_csv_evidence_using_format_alias_and_auto_file_path(): void
    {
        Storage::fake('local');

        $admin = $this->adminToken();

        $create = $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/reconciliation/exports', [
                'featureKey' => 'payroll_run',
                'actionKey' => 'finalize',
                'scopeRef' => '10',
                'format' => 'csv',
                'filterPayload' => [
                    'periods' => [
                        ['userId' => 1],
                        ['userId' => 2],
                    ],
                ],
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $relativePath = (string) $create->json('data.filePath');
        $this->assertStringStartsWith('reconciliation/generated/', $relativePath);
        Storage::disk('local')->assertExists($relativePath);

        $evidenceId = (int) $create->json('data.id');
        $this->assertSame(2, (int) $create->json('data.rowCount'));

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->get('/v1/reconciliation/exports/'.$evidenceId.'/download')
            ->assertOk();
    }

    public function test_finalize_requires_reconciliation_when_enforced(): void
    {
        config()->set('hcm.export_reconciliation.enabled', true);
        config()->set('hcm.export_reconciliation.enforce.payroll_run.finalize', true);

        $this->employeeToken();
        $admin = $this->adminToken();
        $runId = $this->createDraftRun($admin, 2026, 11);

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-runs/'.$runId.'/finalize')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'EXPORT_RECON_REQUIRED');

        $run = HcmPayrollRun::query()->findOrFail($runId);

        ExportReconciliationEvidence::query()->create([
            'company_id' => $run->company_id,
            'feature_key' => 'payroll_run',
            'action_key' => 'finalize',
            'scope_ref' => (string) $run->id,
            'exported_by_user_id' => User::query()->where('email', 'qa.login@example.com')->firstOrFail()->id,
            'exported_at' => now(),
            'file_format' => 'csv',
            'file_path' => 'reconciliation/payroll-run-'.$run->id.'.csv',
            'row_count' => 1,
            'filter_payload' => [],
            'dataset_checksum' => hash('sha256', 'payroll-run-'.$run->id),
            'expires_at' => now()->addMinutes(30),
        ]);

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll-runs/'.$runId.'/finalize')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_invoice_and_payment_actions_require_reconciliation_when_enforced(): void
    {
        config()->set('hcm.export_reconciliation.enabled', true);
        config()->set('hcm.export_reconciliation.enforce.invoice.mark_paid', true);
        config()->set('hcm.export_reconciliation.enforce.payment.verify', true);

        $admin = $this->adminToken();
        $companyId = $this->activeCompanyIdForUser('qa.login@example.com');
        $adminId = User::query()->where('email', 'qa.login@example.com')->firstOrFail()->id;

        $invoice = Invoice::factory()->create([
            'company_id' => $companyId,
            'status' => 'sent',
            'is_paid' => false,
            'paid_date' => null,
        ]);

        $payment = Payment::query()->create([
            'company_id' => $companyId,
            'invoice_id' => $invoice->id,
            'amount' => 100000,
            'currency' => 'IDR',
            'status' => 'pending',
            'payment_method' => 'bank_transfer',
            'gateway' => 'stub',
            'gateway_reference' => 'gw-ref-1',
            'metadata' => ['source' => 'test'],
        ]);

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->putJson('/v1/saas/invoices/'.$invoice->uuid.'/mark-paid')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'EXPORT_RECON_REQUIRED');

        ExportReconciliationEvidence::query()->create([
            'company_id' => $companyId,
            'feature_key' => 'invoice',
            'action_key' => 'mark_paid',
            'scope_ref' => (string) $invoice->id,
            'exported_by_user_id' => $adminId,
            'exported_at' => now(),
            'file_format' => 'csv',
            'file_path' => 'reconciliation/invoice-'.$invoice->id.'.csv',
            'row_count' => 1,
            'filter_payload' => [],
            'dataset_checksum' => hash('sha256', 'invoice-'.$invoice->id),
            'expires_at' => now()->addMinutes(30),
        ]);

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->putJson('/v1/saas/invoices/'.$invoice->uuid.'/mark-paid')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->putJson('/v1/saas/payments/'.$payment->uuid.'/verify')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'EXPORT_RECON_REQUIRED');

        ExportReconciliationEvidence::query()->create([
            'company_id' => $companyId,
            'feature_key' => 'payment',
            'action_key' => 'verify',
            'scope_ref' => (string) $payment->id,
            'exported_by_user_id' => $adminId,
            'exported_at' => now(),
            'file_format' => 'csv',
            'file_path' => 'reconciliation/payment-'.$payment->id.'.csv',
            'row_count' => 1,
            'filter_payload' => [],
            'dataset_checksum' => hash('sha256', 'payment-'.$payment->id),
            'expires_at' => now()->addMinutes(30),
        ]);

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->putJson('/v1/saas/payments/'.$payment->uuid.'/verify')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_non_admin_actions_return_auth_error_before_reconciliation_gate(): void
    {
        config()->set('hcm.export_reconciliation.enabled', true);
        config()->set('hcm.export_reconciliation.enforce.payroll_run.finalize', true);
        config()->set('hcm.export_reconciliation.enforce.invoice.mark_paid', true);
        config()->set('hcm.export_reconciliation.enforce.payment.verify', true);

        $admin = $this->adminToken();
        $employee = $this->employeeToken();
        $companyId = $this->activeCompanyIdForUser('qa.login@example.com');

        $runId = $this->createDraftRun($admin, 2026, 12);
        $invoice = Invoice::factory()->create([
            'company_id' => $companyId,
            'status' => 'sent',
            'is_paid' => false,
        ]);
        $payment = Payment::query()->create([
            'company_id' => $companyId,
            'invoice_id' => $invoice->id,
            'amount' => 100000,
            'currency' => 'IDR',
            'status' => 'pending',
            'payment_method' => 'bank_transfer',
            'gateway' => 'stub',
            'gateway_reference' => 'gw-ref-auth-order',
            'metadata' => ['source' => 'test'],
        ]);

        $this->withHeaders(['Authorization' => 'Bearer '.$employee])
            ->postJson('/v1/hcm/payroll-runs/'.$runId.'/finalize')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');

        $this->withHeaders(['Authorization' => 'Bearer '.$employee])
            ->putJson('/v1/saas/invoices/'.$invoice->uuid.'/mark-paid')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'ADMIN_REQUIRED');

        $this->withHeaders(['Authorization' => 'Bearer '.$employee])
            ->putJson('/v1/saas/payments/'.$payment->uuid.'/verify')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'ADMIN_REQUIRED');
    }

    public function test_payroll_run_export_generates_csv_with_actual_data(): void
    {
        Storage::fake('local');

        $admin = $this->adminToken();

        // Create period and payroll run for current year/month
        $now = now();
        $runId = $this->createDraftRun($admin, (int) $now->year, (int) $now->month);

        // Export payroll run with filter
        $export = $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/reconciliation/exports', [
                'featureKey' => 'payroll_run',
                'actionKey' => 'disburse',
                'scopeRef' => (string) $runId,
                'fileFormat' => 'csv',
                'filterPayload' => [
                    'periods' => [
                        ['userId' => 1],
                        ['userId' => 2],
                    ],
                ],
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $filePath = (string) $export->json('data.filePath');
        $csvContent = Storage::disk('local')->get($filePath);

        // Verify CSV contains payroll line data headers
        $this->assertStringContainsString('run_id', $csvContent);
        $this->assertStringContainsString('user_id', $csvContent);
        $this->assertStringContainsString('component_code', $csvContent);
        $this->assertStringContainsString('amount', $csvContent);
        $this->assertStringContainsString('service_fee_rate_percent', $csvContent);
        $this->assertStringContainsString('service_fee_amount', $csvContent);
        $this->assertStringContainsString('service_fee_billing_month', $csvContent);

        // Verify it's not just metadata
        $this->assertStringNotContainsString('feature_key,action_key', $csvContent);

        echo "\n✅ Payroll export CSV content:\n";
        echo "---\n";
        echo substr($csvContent, 0, 500); // Print first 500 chars
        echo "\n---\n";
    }
}
