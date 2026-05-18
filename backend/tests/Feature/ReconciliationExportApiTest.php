<?php

namespace Tests\Feature;

use App\Models\CompanyUser;
use App\Models\EmployeeProfile;
use App\Models\ExportReconciliationEvidence;
use App\Models\HcmPayrollLine;
use App\Models\HcmPayrollRun;
use App\Models\HcmSalaryComponent;
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
        $this->employeeToken();

        $adminUserId = (int) User::query()->where('email', 'qa.login@example.com')->value('id');
        $employeeUserId = (int) User::query()->where('email', 'recon-employee@example.com')->value('id');
        $companyId = $this->activeCompanyIdForUser('qa.login@example.com');

        EmployeeProfile::query()->where('user_id', $adminUserId)->update([
            'bank_name' => 'BCA',
            'bank_account_no' => '1234567890',
            'bank_branch' => 'Jakarta Pusat',
        ]);
        EmployeeProfile::query()->where('user_id', $employeeUserId)->update([
            'bank_name' => 'Bank Mandiri',
            'bank_account_no' => '9876543210',
            'bank_branch' => 'Jakarta Selatan',
        ]);

        // Create period and payroll run for current year/month
        $now = now();
        $runId = $this->createDraftRun($admin, (int) $now->year, (int) $now->month);

        $netAddition = HcmSalaryComponent::query()->create([
            'company_id' => $companyId,
            'code' => 'test_net_add',
            'name' => 'Test Net Addition',
            'kind' => 'addition',
            'category' => 'other_addition',
            'affects_net_pay' => true,
            'is_active' => true,
        ]);
        $overtimeAddition = HcmSalaryComponent::query()->firstOrCreate(
            ['code' => HcmSalaryComponent::CODE_OVERTIME_PAY],
            [
                'company_id' => $companyId,
                'name' => 'Upah Lembur',
                'kind' => 'addition',
                'category' => 'overtime',
                'affects_net_pay' => true,
                'is_active' => true,
            ],
        );
        $nonNetAddition = HcmSalaryComponent::query()->create([
            'company_id' => $companyId,
            'code' => 'test_non_net_add',
            'name' => 'Test Non Net Addition',
            'kind' => 'addition',
            'category' => 'employer_cost_display',
            'affects_net_pay' => false,
            'is_active' => true,
        ]);
        $netDeduction = HcmSalaryComponent::query()->create([
            'company_id' => $companyId,
            'code' => 'test_net_ded',
            'name' => 'Test Net Deduction',
            'kind' => 'deduction',
            'category' => 'other_deduction',
            'affects_net_pay' => true,
            'is_active' => true,
        ]);

        HcmPayrollLine::query()->create([
            'company_id' => $companyId,
            'hcm_payroll_run_id' => $runId,
            'user_id' => $adminUserId,
            'hcm_salary_component_id' => $netAddition->id,
            'component_code' => 'test_net_add',
            'component_name' => 'Test Net Addition',
            'kind' => 'addition',
            'category' => 'other_addition',
            'amount' => 1000000,
            'sort_order' => 10,
            'meta' => [],
        ]);
        HcmPayrollLine::query()->create([
            'company_id' => $companyId,
            'hcm_payroll_run_id' => $runId,
            'user_id' => $adminUserId,
            'hcm_salary_component_id' => $overtimeAddition->id,
            'component_code' => HcmSalaryComponent::CODE_OVERTIME_PAY,
            'component_name' => 'Upah Lembur',
            'kind' => 'addition',
            'category' => 'overtime',
            'amount' => 150000,
            'sort_order' => 15,
            'meta' => [],
        ]);
        HcmPayrollLine::query()->create([
            'company_id' => $companyId,
            'hcm_payroll_run_id' => $runId,
            'user_id' => $adminUserId,
            'hcm_salary_component_id' => $nonNetAddition->id,
            'component_code' => 'test_non_net_add',
            'component_name' => 'Test Non Net Addition',
            'kind' => 'addition',
            'category' => 'employer_cost_display',
            'amount' => 250000,
            'sort_order' => 20,
            'meta' => [],
        ]);
        HcmPayrollLine::query()->create([
            'company_id' => $companyId,
            'hcm_payroll_run_id' => $runId,
            'user_id' => $employeeUserId,
            'hcm_salary_component_id' => $netDeduction->id,
            'component_code' => 'test_net_ded',
            'component_name' => 'Test Net Deduction',
            'kind' => 'deduction',
            'category' => 'other_deduction',
            'amount' => 200000,
            'sort_order' => 30,
            'meta' => [],
        ]);

        // Export payroll run with filter
        $export = $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/reconciliation/exports', [
                'featureKey' => 'payroll_run',
                'actionKey' => 'disburse',
                'scopeRef' => (string) $runId,
                'fileFormat' => 'csv',
                'filterPayload' => [
                    'periods' => [
                        ['userId' => $adminUserId],
                        ['userId' => $employeeUserId],
                    ],
                ],
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $filePath = (string) $export->json('data.filePath');
        $csvContent = Storage::disk('local')->get($filePath);

        // Verify CSV now contains unified payroll export headers
        $this->assertStringContainsString('payroll_type', $csvContent);
        $this->assertStringContainsString('reference_period', $csvContent);
        $this->assertStringContainsString('reference_id', $csvContent);
        $this->assertStringContainsString('employee_id', $csvContent);
        $this->assertStringContainsString('employee_name', $csvContent);
        $this->assertStringContainsString('bank_name', $csvContent);
        $this->assertStringContainsString('account_number', $csvContent);
        $this->assertStringContainsString('account_holder_name', $csvContent);
        $this->assertStringContainsString('bank_branch', $csvContent);
        $this->assertStringContainsString('gross_total', $csvContent);
        $this->assertStringContainsString('overtime_total', $csvContent);
        $this->assertStringContainsString('deductions_total', $csvContent);
        $this->assertStringContainsString('transfer_amount', $csvContent);
        $this->assertStringContainsString('bank_data_status', $csvContent);
        $this->assertStringContainsString('Recon Admin', $csvContent);
        $this->assertStringContainsString('Recon Employee', $csvContent);
        $this->assertStringContainsString('BCA', $csvContent);
        $this->assertStringContainsString('1234567890', $csvContent);
        $this->assertStringContainsString('Bank Mandiri', $csvContent);
        $this->assertStringContainsString('9876543210', $csvContent);

        $lines = array_values(array_filter(preg_split('/\r\n|\n|\r/', $csvContent) ?: []));
        $this->assertCount(3, $lines, 'Expected one header row plus one payment row per employee.');

        $adminLine = collect($lines)->first(fn (string $line): bool => str_contains($line, 'Recon Admin'));
        $employeeLine = collect($lines)->first(fn (string $line): bool => str_contains($line, 'Recon Employee'));

        $this->assertNotNull($adminLine, 'Expected admin payment row in reconciliation CSV.');
        $this->assertNotNull($employeeLine, 'Expected employee payment row in reconciliation CSV.');

        $adminCols = str_getcsv((string) $adminLine);
        $employeeCols = str_getcsv((string) $employeeLine);

        $this->assertSame('monthly', $adminCols[0] ?? null);
        $this->assertSame(sprintf('%04d-%02d', (int) $now->year, (int) $now->month), $adminCols[1] ?? null);
        $this->assertSame('run:'.$runId, $adminCols[2] ?? null);
        $this->assertSame('Recon Admin', $adminCols[4] ?? null);
        $this->assertSame('BCA', $adminCols[5] ?? null);
        $this->assertSame('1234567890', $adminCols[6] ?? null);
        $this->assertSame('Recon Admin', $adminCols[7] ?? null);
        $this->assertSame(1150000.0, (float) ($adminCols[9] ?? 0));
        $this->assertSame(150000.0, (float) ($adminCols[10] ?? 0));
        $this->assertSame(0.0, (float) ($adminCols[11] ?? 0));
        $this->assertSame(1150000.0, (float) ($adminCols[12] ?? 0));
        $this->assertSame('ready', $adminCols[13] ?? null);

        $this->assertSame('monthly', $employeeCols[0] ?? null);
        $this->assertSame('Recon Employee', $employeeCols[4] ?? null);
        $this->assertSame('Bank Mandiri', $employeeCols[5] ?? null);
        $this->assertSame('9876543210', $employeeCols[6] ?? null);
        $this->assertSame('Recon Employee', $employeeCols[7] ?? null);
        $this->assertSame(0.0, (float) ($employeeCols[9] ?? 0));
        $this->assertSame(0.0, (float) ($employeeCols[10] ?? 0));
        $this->assertSame(200000.0, (float) ($employeeCols[11] ?? 0));
        $this->assertSame(-200000.0, (float) ($employeeCols[12] ?? 0));
        $this->assertSame('ready', $employeeCols[13] ?? null);

        // Verify it's not just metadata
        $this->assertStringNotContainsString('feature_key,action_key', $csvContent);
        $this->assertStringNotContainsString('METADATA', $csvContent);
        $this->assertStringNotContainsString('SUBTOTAL', $csvContent);
        $this->assertStringNotContainsString('GRAND_TOTAL', $csvContent);

        echo "\n✅ Payroll export CSV content:\n";
        echo "---\n";
        echo substr($csvContent, 0, 500); // Print first 500 chars
        echo "\n---\n";
    }

    public function test_thr_batch_export_generates_structured_payroll_rows(): void
    {
        Storage::fake('local');

        $employeeToken = $this->employeeToken();
        $admin = $this->adminToken();
        $employee = User::query()->where('email', 'recon-employee@example.com')->firstOrFail();

        EmployeeProfile::query()->where('user_id', $employee->id)->update([
            'hire_date' => '2024-01-15',
            'base_salary' => 3500000,
            'bank_name' => 'BNI',
            'bank_account_no' => '99887766',
            'bank_branch' => 'Bandung',
        ]);

        unset($employeeToken);

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->putJson('/v1/hcm/payroll/thr-settings/2028', [
                'eidDate' => '2028-04-10',
                'paymentDate' => '2028-04-05',
                'calculationCutoffDate' => '2028-04-09',
            ])
            ->assertOk();

        $generate = $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/hcm/payroll/thr-batch/generate', ['calendarYear' => 2028])
            ->assertOk();

        $batchId = (int) $generate->json('data.batch.id');
        $eligibleLine = collect($generate->json('data.lines'))
            ->first(fn (array $line): bool => (bool) ($line['eligible'] ?? false) && (int) ($line['userId'] ?? 0) === (int) $employee->id);

        $this->assertNotNull($eligibleLine);

        $export = $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/reconciliation/exports', [
                'featureKey' => 'thr_batch',
                'actionKey' => 'disburse',
                'scopeRef' => (string) $batchId,
                'fileFormat' => 'csv',
                'filterPayload' => [
                    'lineIds' => [(int) ($eligibleLine['id'] ?? 0)],
                ],
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $csvContent = Storage::disk('local')->get((string) $export->json('data.filePath'));
        $this->assertStringContainsString('payroll_type,reference_period,reference_id', $csvContent);
        $this->assertStringContainsString('thr', $csvContent);
        $this->assertStringContainsString('thr_batch:'.$batchId, $csvContent);
        $this->assertStringContainsString('Recon Employee', $csvContent);
        $this->assertStringContainsString('BNI', $csvContent);
        $this->assertStringContainsString('99887766', $csvContent);
    }

    public function test_pkwt_compensation_export_generates_structured_payroll_rows(): void
    {
        Storage::fake('local');

        $employeeToken = $this->employeeToken();
        $admin = $this->adminToken();
        $employee = User::query()->where('email', 'recon-employee@example.com')->firstOrFail();

        EmployeeProfile::query()->where('user_id', $employee->id)->update([
            'contract_type' => 'pkwt',
            'contract_start_date' => '2025-05-01',
            'contract_end_date' => '2026-05-31',
            'base_salary' => 4200000,
            'bank_name' => 'BRI',
            'bank_account_no' => '44556677',
            'bank_branch' => 'Surabaya',
        ]);

        unset($employeeToken);

        $export = $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->postJson('/v1/reconciliation/exports', [
                'featureKey' => 'pkwt_compensation',
                'actionKey' => 'post_payroll',
                'scopeRef' => '2026-05',
                'fileFormat' => 'csv',
                'filterPayload' => [
                    'lineIds' => [$employee->id],
                ],
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $csvContent = Storage::disk('local')->get((string) $export->json('data.filePath'));
        $this->assertStringContainsString('payroll_type,reference_period,reference_id', $csvContent);
        $this->assertStringContainsString('pkwt_compensation', $csvContent);
        $this->assertStringContainsString('period:2026-05', $csvContent);
        $this->assertStringContainsString('Recon Employee', $csvContent);
        $this->assertStringContainsString('BRI', $csvContent);
        $this->assertStringContainsString('44556677', $csvContent);
    }
}
