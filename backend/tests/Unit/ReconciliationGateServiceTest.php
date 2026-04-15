<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\ExportReconciliationEvidence;
use App\Models\User;
use App\Services\Reconciliation\Exceptions\ExportReconciliationException;
use App\Services\Reconciliation\ReconciliationGateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReconciliationGateServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_throws_required_when_evidence_is_missing(): void
    {
        $service = new ReconciliationGateService();

        try {
            $service->assertCanProceed(
                companyId: 1,
                featureKey: ExportReconciliationEvidence::FEATURE_PAYROLL_RUN,
                actionKey: ExportReconciliationEvidence::ACTION_FINALIZE,
                scopeRef: 'run:11'
            );

            $this->fail('Expected ExportReconciliationException was not thrown.');
        } catch (ExportReconciliationException $e) {
            $this->assertSame('EXPORT_RECON_REQUIRED', $e->errorCode());
            $this->assertSame(422, $e->status());
        }
    }

    public function test_it_throws_expired_when_latest_evidence_is_expired(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        ExportReconciliationEvidence::query()->create([
            'company_id' => $company->id,
            'feature_key' => ExportReconciliationEvidence::FEATURE_PAYROLL_RUN,
            'action_key' => ExportReconciliationEvidence::ACTION_FINALIZE,
            'scope_ref' => 'run:24',
            'exported_by_user_id' => $user->id,
            'exported_at' => now()->subMinutes(20),
            'file_format' => 'csv',
            'file_path' => 'private/exports/reconciliation/run-24.csv',
            'row_count' => 10,
            'filter_payload' => ['periodYear' => 2026, 'periodMonth' => 4],
            'dataset_checksum' => hash('sha256', 'seed-24'),
            'expires_at' => now()->subMinute(),
        ]);

        $service = new ReconciliationGateService();

        $this->expectException(ExportReconciliationException::class);
        $this->expectExceptionMessage('Export reconciliation evidence has expired. Please export latest data.');
        try {
            $service->assertCanProceed(
                companyId: (int) $company->id,
                featureKey: ExportReconciliationEvidence::FEATURE_PAYROLL_RUN,
                actionKey: ExportReconciliationEvidence::ACTION_FINALIZE,
                scopeRef: 'run:24',
                expectedFilterPayload: ['periodYear' => 2026, 'periodMonth' => 4]
            );
        } catch (ExportReconciliationException $e) {
            $this->assertSame('EXPORT_RECON_EXPIRED', $e->errorCode());
            throw $e;
        }
    }

    public function test_it_throws_scope_mismatch_when_filter_payload_does_not_match(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        ExportReconciliationEvidence::query()->create([
            'company_id' => $company->id,
            'feature_key' => ExportReconciliationEvidence::FEATURE_INVOICE,
            'action_key' => ExportReconciliationEvidence::ACTION_MARK_PAID,
            'scope_ref' => 'invoice:81',
            'exported_by_user_id' => $user->id,
            'exported_at' => now()->subMinutes(3),
            'file_format' => 'xlsx',
            'file_path' => 'private/exports/reconciliation/invoice-81.xlsx',
            'row_count' => 1,
            'filter_payload' => ['status' => 'pending'],
            'dataset_checksum' => hash('sha256', 'seed-81'),
            'expires_at' => now()->addMinutes(30),
        ]);

        $service = new ReconciliationGateService();

        try {
            $service->assertCanProceed(
                companyId: (int) $company->id,
                featureKey: ExportReconciliationEvidence::FEATURE_INVOICE,
                actionKey: ExportReconciliationEvidence::ACTION_MARK_PAID,
                scopeRef: 'invoice:81',
                expectedFilterPayload: ['status' => 'unpaid']
            );

            $this->fail('Expected ExportReconciliationException was not thrown.');
        } catch (ExportReconciliationException $e) {
            $this->assertSame('EXPORT_RECON_SCOPE_MISMATCH', $e->errorCode());
        }
    }

    public function test_it_returns_evidence_when_gate_is_valid(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $checksum = hash('sha256', 'seed-900');
        ExportReconciliationEvidence::query()->create([
            'company_id' => $company->id,
            'feature_key' => ExportReconciliationEvidence::FEATURE_PAYMENT,
            'action_key' => ExportReconciliationEvidence::ACTION_VERIFY,
            'scope_ref' => 'payment:900',
            'exported_by_user_id' => $user->id,
            'exported_at' => now()->subMinutes(1),
            'file_format' => 'csv',
            'file_path' => 'private/exports/reconciliation/payment-900.csv',
            'row_count' => 1,
            'filter_payload' => ['status' => 'pending'],
            'dataset_checksum' => $checksum,
            'expires_at' => now()->addMinutes(10),
        ]);

        $service = new ReconciliationGateService();
        $evidence = $service->assertCanProceed(
            companyId: (int) $company->id,
            featureKey: ExportReconciliationEvidence::FEATURE_PAYMENT,
            actionKey: ExportReconciliationEvidence::ACTION_VERIFY,
            scopeRef: 'payment:900',
            expectedFilterPayload: ['status' => 'pending'],
            currentDatasetChecksum: $checksum,
            strictChecksum: true
        );

        $this->assertSame('payment:900', $evidence->scope_ref);
        $this->assertSame('verify', $evidence->action_key);
    }
}
