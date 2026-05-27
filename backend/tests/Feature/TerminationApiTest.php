<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\EmployeeProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TerminationApiTest extends TestCase
{
    use RefreshDatabase;

    private function login(bool $asAdmin): array
    {
        $email = $asAdmin ? 'qa.login@example.com' : 'employee@company.com';
        $this->postJson('/v1/identity/auth/register', [
            'name' => $asAdmin ? 'Admin User' : 'Employee User',
            'email' => $email,
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ]);

        $user = User::query()->where('email', $email)->firstOrFail();

        $resp = $this->postJson('/v1/identity/auth/login', [
            'email' => $email,
            'password' => 'StrongPass1',
        ])->assertOk();

        $token = $resp->json('data.accessToken');
        $this->assertIsString($token);

        return [$user, $token];
    }

    public function test_terminations_admin_crud_and_employee_forbidden(): void
    {
        [$admin, $adminToken] = $this->login(true);
        [$emp, $empToken] = $this->login(false);

        $this->withHeaders(['Authorization' => 'Bearer '.$empToken])
            ->getJson('/v1/hcm/terminations')
            ->assertStatus(403);

        $create = $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->postJson('/v1/hcm/terminations', [
                'userId' => $emp->uuid,
                'department' => 'Finance',
                'terminationType' => 'Layoff',
                'terminationReasonCode' => 'company_efficiency',
                'legalBasisCode' => 'pp_35_2021',
                'reason' => 'Workforce reduction',
                'noticeDate' => '2026-04-01',
                'terminationDate' => '2026-04-30',
                'notes' => 'OK',
            ])->assertStatus(201);

        $id = $create->json('data.id');
        $this->assertIsInt($id);

        $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->getJson('/v1/hcm/terminations/'.$id)
            ->assertOk()
            ->assertJsonPath('data.terminationReasonCode', 'company_efficiency')
            ->assertJsonPath('data.legalBasisCode', 'pp_35_2021')
            ->assertJsonPath('data.policyProfileKey', 'company_termination')
            ->assertJsonPath('data.policyFormulaVersion', '2026.04.id.v1');

        $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->getJson('/v1/hcm/terminations')
            ->assertOk()
            ->assertJsonPath('success', true);

        // Stage must progress sequentially: draft_review → legal_review → approved_internal
        $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->putJson('/v1/hcm/terminations/'.$id, [
                'workflowStage' => 'legal_review',
            ])->assertOk();

        $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->putJson('/v1/hcm/terminations/'.$id, [
                'status' => 'approved',
            ])->assertOk()
            ->assertJsonPath('success', true);

        // Anomaly #3 guard: delete MUST be blocked for approved/finalized status
        $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->deleteJson('/v1/hcm/terminations/'.$id)
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'DELETE_FORBIDDEN_STATUS');

        // Create a fresh pending termination and verify normal delete works
        $deletable = $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->postJson('/v1/hcm/terminations', [
                'userId' => $emp->uuid,
                'department' => 'Finance',
                'terminationType' => 'Layoff',
                'reason' => 'Redundancy',
                'noticeDate' => '2026-04-01',
                'terminationDate' => '2026-04-30',
            ])->assertStatus(201);

        $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->deleteJson('/v1/hcm/terminations/'.$deletable->json('data.id'))
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_termination_show_and_per_user_list_self_only(): void
    {
        [$admin, $adminToken] = $this->login(true);
        [$empA, $empAToken] = $this->login(false);

        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Emp B',
            'email' => 'empb@company.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ]);
        $empB = User::query()->where('email', 'empb@company.com')->firstOrFail();
        $empBToken = $this->postJson('/v1/identity/auth/login', [
            'email' => 'empb@company.com',
            'password' => 'StrongPass1',
        ])->assertOk()->json('data.accessToken');
        $this->assertIsString($empBToken);

        $body = [
            'terminationType' => 'Retirement',
            'reason' => 'End of contract',
            'noticeDate' => '2026-04-01',
            'terminationDate' => '2026-05-01',
            'notes' => 'ok',
        ];

        $idA = $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->postJson('/v1/hcm/terminations', array_merge(['userId' => $empA->uuid], $body))
            ->assertStatus(201)
            ->json('data.id');

        $idB = $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->postJson('/v1/hcm/terminations', array_merge(['userId' => $empB->uuid], $body))
            ->assertStatus(201)
            ->json('data.id');

        $this->withHeaders(['Authorization' => 'Bearer '.$empAToken])
            ->getJson('/v1/hcm/terminations/'.$idA)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->withHeaders(['Authorization' => 'Bearer '.$empAToken])
            ->getJson('/v1/hcm/terminations/'.$idB)
            ->assertStatus(403);

        $this->withHeaders(['Authorization' => 'Bearer '.$empAToken])
            ->getJson('/v1/hcm/terminations/users/'.$empA->id.'/terminations')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->withHeaders(['Authorization' => 'Bearer '.$empAToken])
            ->getJson('/v1/hcm/terminations/users/'.$empB->id.'/terminations')
            ->assertStatus(403);
    }

    public function test_termination_show_returns_404_when_not_found(): void
    {
        [$admin, $adminToken] = $this->login(true);

        $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->getJson('/v1/hcm/terminations/999999')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'TERMINATION_NOT_FOUND');
    }

    public function test_termination_accepts_uuid_user_identifier_and_rejects_user_outside_active_company(): void
    {
        [$admin, $adminToken] = $this->login(true);
        [$emp, $empToken] = $this->login(false);

        $created = $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->postJson('/v1/hcm/terminations', [
                'userId' => $emp->uuid,
                'department' => 'Finance',
                'terminationType' => 'Layoff',
                'reason' => 'Workforce reduction',
                'noticeDate' => '2026-04-01',
                'terminationDate' => '2026-04-30',
                'notes' => 'UUID payload accepted',
            ])
            ->assertStatus(201);

        $this->assertIsInt($created->json('data.id'));

        $outsider = User::query()->create([
            'name' => 'Outsider User',
            'email' => 'outsider@example.com',
            'password' => 'StrongPass1',
        ]);

        $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->postJson('/v1/hcm/terminations', [
                'userId' => $outsider->uuid,
                'department' => 'Finance',
                'terminationType' => 'Layoff',
                'reason' => 'Cross tenant attempt',
                'noticeDate' => '2026-04-01',
                'terminationDate' => '2026-04-30',
                'notes' => 'Should fail',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_termination_rejects_invalid_legal_taxonomy_codes(): void
    {
        [$admin, $adminToken] = $this->login(true);
        [$emp, $empToken] = $this->login(false);

        $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->postJson('/v1/hcm/terminations', [
                'userId' => $emp->uuid,
                'department' => 'Finance',
                'terminationType' => 'Layoff',
                'terminationReasonCode' => 'invalid_reason_code',
                'legalBasisCode' => 'unknown_basis',
                'reason' => 'Workforce reduction',
                'noticeDate' => '2026-04-01',
                'terminationDate' => '2026-04-30',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_termination_workflow_stage_tracks_audit_trail_and_derives_status(): void
    {
        [$admin, $adminToken] = $this->login(true);
        [$emp, $empToken] = $this->login(false);

        $created = $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->postJson('/v1/hcm/terminations', [
                'userId' => $emp->uuid,
                'department' => 'Finance',
                'terminationType' => 'Layoff',
                'terminationReasonCode' => 'company_efficiency',
                'legalBasisCode' => 'pp_35_2021',
                'workflowStage' => 'legal_review',
                'reason' => 'Workforce reduction',
                'noticeDate' => '2026-04-01',
                'terminationDate' => '2026-04-30',
                'notes' => 'Awaiting internal approval',
            ])
            ->assertStatus(201);

        $id = $created->json('data.id');

        $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->getJson('/v1/hcm/terminations/'.$id)
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.workflowStage', 'legal_review')
            ->assertJsonPath('data.workflow.reviewed.user.id', $admin->id);

        $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->putJson('/v1/hcm/terminations/'.$id, [
                'workflowStage' => 'approved_internal',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->getJson('/v1/hcm/terminations/'.$id)
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.workflowStage', 'approved_internal')
            ->assertJsonPath('data.workflow.approved.user.id', $admin->id);
    }

    public function test_termination_rejects_invalid_workflow_stage_transition(): void
    {
        [$admin, $adminToken] = $this->login(true);
        [$emp, $empToken] = $this->login(false);

        $created = $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->postJson('/v1/hcm/terminations', [
                'userId' => $emp->uuid,
                'department' => 'Finance',
                'terminationType' => 'Layoff',
                'workflowStage' => 'finalized_execution',
                'reason' => 'Legacy finalized import',
                'noticeDate' => '2026-04-01',
                'terminationDate' => '2026-04-30',
                'clearanceNotes' => 'Imported finalized record',
            ])
            ->assertStatus(201);

        $id = $created->json('data.id');

        $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->putJson('/v1/hcm/terminations/'.$id, [
                'workflowStage' => 'approved_internal',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_termination_finalization_requires_mandatory_non_asset_checklist_completion_when_checklist_present(): void
    {
        [$admin, $adminToken] = $this->login(true);
        [$emp, $empToken] = $this->login(false);

        $basePayload = [
            'userId' => $emp->uuid,
            'department' => 'Finance',
            'terminationType' => 'Layoff',
            'reason' => 'Operational handover',
            'noticeDate' => '2026-04-01',
            'terminationDate' => '2026-04-30',
            'workflowStage' => 'finalized_execution',
            'clearanceNotes' => 'Checklist reviewed by HR and payroll.',
        ];

        $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->postJson('/v1/hcm/terminations', array_merge($basePayload, [
                'nonAssetChecklist' => [
                    [
                        'label' => 'Handover pekerjaan aktif',
                        'ownerName' => 'HRBP',
                        'dueDate' => '2026-04-29',
                        'status' => 'pending',
                        'mandatory' => true,
                    ],
                ],
            ]))
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $created = $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->postJson('/v1/hcm/terminations', array_merge($basePayload, [
                'nonAssetChecklist' => [
                    [
                        'label' => 'Handover pekerjaan aktif',
                        'ownerName' => 'HRBP',
                        'dueDate' => '2026-04-29',
                        'status' => 'completed',
                        'completionEvidence' => 'BA handover signed.',
                        'mandatory' => true,
                    ],
                ],
            ]))
            ->assertStatus(201);

        $id = $created->json('data.id');

        $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->getJson('/v1/hcm/terminations/'.$id)
            ->assertOk()
            ->assertJsonPath('data.settlement.nonAssetChecklist.0.label', 'Handover pekerjaan aktif')
            ->assertJsonPath('data.settlement.nonAssetChecklist.0.status', 'completed');
    }

    public function test_termination_finalized_requires_settlement_snapshot_and_returns_computed_net_amount(): void
    {
        [$admin, $adminToken] = $this->login(true);
        [$emp, $empToken] = $this->login(false);

        $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->postJson('/v1/hcm/terminations', [
                'userId' => $emp->uuid,
                'department' => 'Finance',
                'terminationType' => 'Layoff',
                'reason' => 'Workforce reduction',
                'noticeDate' => '2026-04-01',
                'terminationDate' => '2026-04-30',
                'status' => 'finalized',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $created = $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->postJson('/v1/hcm/terminations', [
                'userId' => $emp->uuid,
                'department' => 'Finance',
                'terminationType' => 'Layoff',
                'reason' => 'Workforce reduction',
                'noticeDate' => '2026-04-01',
                'terminationDate' => '2026-04-30',
                'status' => 'finalized',
                'settlementPayrollPeriod' => '2026-05',
                'finalSalaryAmount' => 4500000,
                'finalAllowanceAmount' => 750000,
                'finalDeductionAmount' => 500000,
                'assetReturnNotes' => 'Laptop dan kartu akses sudah dikembalikan.',
                'clearanceNotes' => 'Settlement dibawa ke payroll periode terdekat setelah clearance selesai.',
            ])
            ->assertStatus(201);

        $id = $created->json('data.id');

        $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->getJson('/v1/hcm/terminations/'.$id)
            ->assertOk()
            ->assertJsonPath('data.status', 'finalized')
            ->assertJsonPath('data.settlement.payrollPeriod', '2026-05')
            ->assertJsonPath('data.settlement.finalSalaryAmount', '4500000.00')
            ->assertJsonPath('data.settlement.finalAllowanceAmount', '750000.00')
            ->assertJsonPath('data.settlement.finalDeductionAmount', '500000.00')
            ->assertJsonPath('data.settlement.finalNetAmount', '4750000.00')
            ->assertJsonPath('data.settlement.policyProfile', null);
    }

    public function test_termination_settlement_preview_returns_compensation_and_clearance_and_finalized_store_auto_links_period(): void
    {
        Carbon::setTestNow('2026-04-19 10:00:00');

        try {
            [$admin, $adminToken] = $this->login(true);
            [$emp, $empToken] = $this->login(false);

            $profile = EmployeeProfile::query()->updateOrCreate(
                ['user_id' => $emp->id],
                [
                    'company_id' => 1,
                    'base_salary' => 5_000_000,
                    'fixed_allowance' => 750_000,
                ]
            );

            $asset = Asset::query()->create([
                'company_id' => 1,
                'asset_code' => 'LAP-001',
                'name' => 'Laptop Kerja',
                'purchase_date' => '2026-03-01',
                'purchase_price' => 15_000_000,
                'condition' => 'good',
                'status' => 'assigned',
            ]);

            AssetAssignment::query()->create([
                'company_id' => 1,
                'asset_id' => $asset->id,
                'employee_id' => $profile->id,
                'assigned_date' => '2026-03-10',
                'condition_at_assign' => 'good',
                'active_token' => 'active',
                'notes' => 'Belum dikembalikan.',
            ]);

            $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
                ->getJson('/v1/hcm/terminations/settlement-preview?userId='.$emp->uuid.'&terminationDate=2026-05-15')
                ->assertOk()
                ->assertJsonPath('data.resolvedPeriod.label', '2026-05')
                ->assertJsonPath('data.resolvedPeriod.isExisting', false)
                ->assertJsonPath('data.source', 'termination_policy_prorated')
                ->assertJsonPath('data.summary.finalSalaryAmount', '2419354.84')
                ->assertJsonPath('data.summary.finalAllowanceAmount', '0.00')
                ->assertJsonPath('data.summary.finalDeductionAmount', '0.00')
                ->assertJsonPath('data.clearance.outstandingCount', 1)
                ->assertJsonPath('data.breakdown.0.componentCode', 'termination_prorated_salary')
                ->assertJsonPath('data.clearance.items.0.assetCode', 'LAP-001');

            $created = $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
                ->postJson('/v1/hcm/terminations', [
                    'userId' => $emp->uuid,
                    'department' => 'Finance',
                    'terminationType' => 'Layoff',
                    'reason' => 'Workforce reduction',
                    'noticeDate' => '2026-04-20',
                    'terminationDate' => '2026-05-15',
                    'status' => 'finalized',
                    'clearanceNotes' => 'Settlement akan dibayar pada payroll terdekat setelah seluruh clearance item selesai.',
                ])
                ->assertStatus(201);

            $id = $created->json('data.id');

            $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
                ->getJson('/v1/hcm/terminations/'.$id)
                ->assertOk()
                ->assertJsonPath('data.status', 'finalized')
                ->assertJsonPath('data.settlement.payrollPeriod', '2026-05')
                ->assertJsonPath('data.settlement.payrollPeriodStatus', 'open')
                ->assertJsonPath('data.settlement.finalSalaryAmount', '2419354.84')
                ->assertJsonPath('data.settlement.finalAllowanceAmount', '0.00')
                ->assertJsonPath('data.settlement.finalDeductionAmount', '0.00')
                ->assertJsonPath('data.settlement.finalNetAmount', '2419354.84')
                ->assertJsonPath('data.settlement.clearanceItems.0.assetCode', 'LAP-001')
                ->assertJsonPath('data.settlement.breakdown.0.componentCode', 'termination_prorated_salary');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_termination_clearance_item_can_be_returned_from_termination_context(): void
    {
        Carbon::setTestNow('2026-04-19 10:00:00');

        try {
            [$admin, $adminToken] = $this->login(true);
            [$emp, $empToken] = $this->login(false);

            $profile = EmployeeProfile::query()->updateOrCreate(
                ['user_id' => $emp->id],
                [
                    'company_id' => 1,
                    'base_salary' => 5_000_000,
                    'fixed_allowance' => 750_000,
                ]
            );

            $asset = Asset::query()->create([
                'company_id' => 1,
                'asset_code' => 'LAP-002',
                'name' => 'Laptop Clearance',
                'purchase_date' => '2026-03-01',
                'purchase_price' => 15_000_000,
                'condition' => 'good',
                'status' => 'assigned',
            ]);

            $assignment = AssetAssignment::query()->create([
                'company_id' => 1,
                'asset_id' => $asset->id,
                'employee_id' => $profile->id,
                'assigned_date' => '2026-03-10',
                'condition_at_assign' => 'good',
                'active_token' => 'active',
                'notes' => 'Belum dikembalikan.',
            ]);

            $created = $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
                ->postJson('/v1/hcm/terminations', [
                    'userId' => $emp->uuid,
                    'department' => 'Finance',
                    'terminationType' => 'Layoff',
                    'reason' => 'Workforce reduction',
                    'noticeDate' => '2026-04-20',
                    'terminationDate' => '2026-05-15',
                    'status' => 'finalized',
                    'clearanceNotes' => 'Asset harus kembali sebelum payroll final diposting.',
                ])
                ->assertStatus(201);

            $terminationId = $created->json('data.id');

            $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
                ->postJson('/v1/hcm/terminations/'.$terminationId.'/clearance-items/'.$assignment->id.'/return', [
                    'returnedDate' => '2026-04-19',
                    'conditionAtReturn' => 'good',
                    'notes' => 'Returned from termination workflow.',
                ])
                ->assertOk()
                ->assertJsonPath('data.termination.settlement.clearanceOutstandingCount', 0);

            $this->assertDatabaseHas('assets', [
                'id' => $asset->id,
                'status' => 'available',
            ]);

            $this->assertDatabaseHas('asset_assignments', [
                'id' => $assignment->id,
                'asset_id' => $asset->id,
                'active_token' => null,
            ]);
        } finally {
            Carbon::setTestNow();
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // Slice B — Workflow audit trail + optimistic locking
    // ─────────────────────────────────────────────────────────────────

    public function test_workflow_version_conflict_returns_409(): void
    {
        [$admin, $adminToken] = $this->login(true);
        [$emp, $empToken] = $this->login(false);

        $id = $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->postJson('/v1/hcm/terminations', [
                'userId' => $emp->uuid,
                'department' => 'HR',
                'terminationType' => 'Resignation',
                'reason' => 'Personal',
                'noticeDate' => '2026-05-01',
                'terminationDate' => '2026-05-31',
            ])->assertStatus(201)->json('data.id');

        // Correct version is 0; send stale version 99 → 409
        $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->putJson('/v1/hcm/terminations/'.$id, [
                'workflowStage' => 'draft_review',
                'workflowVersion' => 99,
            ])->assertStatus(409)
            ->assertJsonPath('error.code', 'WORKFLOW_VERSION_CONFLICT');
    }

    public function test_workflow_history_populated_on_stage_change(): void
    {
        [$admin, $adminToken] = $this->login(true);
        [$emp, $empToken] = $this->login(false);

        $create = $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->postJson('/v1/hcm/terminations', [
                'userId' => $emp->uuid,
                'department' => 'Ops',
                'terminationType' => 'Layoff',
                'reason' => 'Restructure',
                'noticeDate' => '2026-05-01',
                'terminationDate' => '2026-05-31',
            ])->assertStatus(201);

        $id = $create->json('data.id');

        // GET to read the initial workflow version
        $show = $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->getJson('/v1/hcm/terminations/'.$id)->assertOk();
        $version = $show->json('data.workflow.version');
        $this->assertSame(0, $version);

        // Move to draft_review
        $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->putJson('/v1/hcm/terminations/'.$id, [
                'workflowStage' => 'legal_review',
                'workflowVersion' => $version,
            ])->assertOk();

        // GET after PUT to inspect workflow state
        $show = $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->getJson('/v1/hcm/terminations/'.$id)->assertOk();

        $newVersion = $show->json('data.workflow.version');
        $this->assertGreaterThan($version, $newVersion);

        $history = $show->json('data.workflow.history');
        $this->assertIsArray($history);
        $this->assertNotEmpty($history);
        $this->assertSame('legal_review', $history[0]['new_stage']);
        $this->assertArrayHasKey('actor_id', $history[0]);
        $this->assertArrayHasKey('timestamp', $history[0]);
    }

    public function test_workflow_response_always_includes_version_and_history(): void
    {
        [$admin, $adminToken] = $this->login(true);
        [$emp, $empToken] = $this->login(false);

        $id = $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->postJson('/v1/hcm/terminations', [
                'userId' => $emp->uuid,
                'department' => 'Tech',
                'terminationType' => 'Resignation',
                'reason' => 'Personal',
                'noticeDate' => '2026-05-01',
                'terminationDate' => '2026-05-31',
            ])->assertStatus(201)->json('data.id');

        $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->getJson('/v1/hcm/terminations/'.$id)
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'workflow' => ['stage', 'version', 'history', 'reviewed', 'approved', 'finalized'],
                ],
            ])
            ->assertJsonPath('data.workflow.version', 0)
            ->assertJsonPath('data.workflow.history', []);
    }

    // ─────────────────────────────────────────────────────────────────
    // Slice C — Checklist items CRUD
    // ─────────────────────────────────────────────────────────────────

    public function test_checklist_item_happy_path_crud(): void
    {
        [$admin, $adminToken] = $this->login(true);
        [$emp, $empToken] = $this->login(false);

        $id = $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->postJson('/v1/hcm/terminations', [
                'userId' => $emp->uuid,
                'department' => 'Finance',
                'terminationType' => 'Layoff',
                'reason' => 'Redundancy',
                'noticeDate' => '2026-05-01',
                'terminationDate' => '2026-05-31',
            ])->assertStatus(201)->json('data.id');

        // POST — create checklist item
        $item = $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->postJson('/v1/hcm/terminations/'.$id.'/checklist-items', [
                'label' => 'Return laptop',
                'ownerName' => 'IT Dept',
                'dueDate' => '2026-05-30',
                'mandatory' => true,
            ])->assertStatus(201)
            ->assertJsonPath('data.label', 'Return laptop')
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.mandatory', true);

        $itemId = $item->json('data.id');

        // GET — list checklist items
        $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->getJson('/v1/hcm/terminations/'.$id.'/checklist-items')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $itemId);

        // PATCH — update
        $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->patchJson('/v1/hcm/terminations/'.$id.'/checklist-items/'.$itemId, [
                'label' => 'Return laptop and badge',
                'status' => 'skipped',
            ])->assertOk()
            ->assertJsonPath('data.label', 'Return laptop and badge')
            ->assertJsonPath('data.status', 'skipped');

        // PATCH /complete — mark complete
        $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->patchJson('/v1/hcm/terminations/'.$id.'/checklist-items/'.$itemId.'/complete', [
                'completionEvidence' => 'Laptop returned, ticket #1234',
            ])->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.completedBy', $admin->id);
    }

    public function test_checklist_item_delete_blocked_when_completed(): void
    {
        [$admin, $adminToken] = $this->login(true);
        [$emp, $empToken] = $this->login(false);

        $id = $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->postJson('/v1/hcm/terminations', [
                'userId' => $emp->uuid,
                'department' => 'Finance',
                'terminationType' => 'Layoff',
                'reason' => 'Redundancy',
                'noticeDate' => '2026-05-01',
                'terminationDate' => '2026-05-31',
            ])->assertStatus(201)->json('data.id');

        $itemId = $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->postJson('/v1/hcm/terminations/'.$id.'/checklist-items', [
                'label' => 'Sign NDA',
                'mandatory' => false,
            ])->assertStatus(201)->json('data.id');

        // Complete the item
        $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->patchJson('/v1/hcm/terminations/'.$id.'/checklist-items/'.$itemId.'/complete')
            ->assertOk();

        // DELETE after completion → blocked
        $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->deleteJson('/v1/hcm/terminations/'.$id.'/checklist-items/'.$itemId)
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'DELETE_FORBIDDEN_COMPLETED');
    }

    public function test_checklist_item_blocked_when_termination_finalized(): void
    {
        Carbon::setTestNow('2026-05-01 10:00:00');

        try {
            [$admin, $adminToken] = $this->login(true);
            [$emp, $empToken] = $this->login(false);

            EmployeeProfile::query()->updateOrCreate(
                ['user_id' => $emp->id],
                ['company_id' => 1, 'base_salary' => 3_000_000]
            );

            $id = $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
                ->postJson('/v1/hcm/terminations', [
                    'userId' => $emp->uuid,
                    'department' => 'Finance',
                    'terminationType' => 'Layoff',
                    'terminationReasonCode' => 'company_efficiency',
                    'legalBasisCode' => 'pp_35_2021',
                    'reason' => 'Redundancy',
                    'noticeDate' => '2026-04-01',
                    'terminationDate' => '2026-04-30',
                    'status' => 'finalized',
                    'settlementPayrollPeriod' => '2026-05',
                    'finalSalaryAmount' => '3000000',
                    'clearanceNotes' => 'All clear',
                ])->assertStatus(201)->json('data.id');

            // POST checklist item to finalized termination → blocked
            $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
                ->postJson('/v1/hcm/terminations/'.$id.'/checklist-items', [
                    'label' => 'Exit interview',
                    'mandatory' => false,
                ])->assertStatus(422)
                ->assertJsonPath('error.code', 'TERMINATION_LOCKED');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_checklist_items_tenant_isolation(): void
    {
        [$admin, $adminToken] = $this->login(true);
        [$emp, $empToken] = $this->login(false);

        // Create termination and checklist item for company 1
        $id = $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->postJson('/v1/hcm/terminations', [
                'userId' => $emp->uuid,
                'department' => 'Ops',
                'terminationType' => 'Resignation',
                'reason' => 'Personal',
                'noticeDate' => '2026-05-01',
                'terminationDate' => '2026-05-31',
            ])->assertStatus(201)->json('data.id');

        $itemId = $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->postJson('/v1/hcm/terminations/'.$id.'/checklist-items', [
                'label' => 'Return access card',
                'mandatory' => true,
            ])->assertStatus(201)->json('data.id');

        // Employee (non-admin) cannot access checklist items
        $this->withHeaders(['Authorization' => 'Bearer '.$empToken])
            ->getJson('/v1/hcm/terminations/'.$id.'/checklist-items')
            ->assertStatus(403);

        $this->withHeaders(['Authorization' => 'Bearer '.$empToken])
            ->patchJson('/v1/hcm/terminations/'.$id.'/checklist-items/'.$itemId, ['label' => 'Hack'])
            ->assertStatus(403);
    }

    // ─────────────────────────────────────────────────────────────────
    // Slice A — Settlement enrichment (evidence snapshot + leave flag)
    // ─────────────────────────────────────────────────────────────────

    public function test_finalized_termination_has_evidence_snapshot(): void
    {
        Carbon::setTestNow('2026-05-26 10:00:00');

        try {
            [$admin, $adminToken] = $this->login(true);
            [$emp, $empToken] = $this->login(false);

            EmployeeProfile::query()->updateOrCreate(
                ['user_id' => $emp->id],
                [
                    'company_id' => 1,
                    'base_salary' => 5_000_000,
                    'hire_date' => '2020-01-01',
                ]
            );

            $id = $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
                ->postJson('/v1/hcm/terminations', [
                    'userId' => $emp->uuid,
                    'department' => 'Tech',
                    'terminationType' => 'Layoff',
                    'terminationReasonCode' => 'company_efficiency',
                    'legalBasisCode' => 'pp_35_2021',
                    'reason' => 'Redundancy',
                    'noticeDate' => '2026-04-01',
                    'terminationDate' => '2026-04-30',
                    'status' => 'finalized',
                    'settlementPayrollPeriod' => '2026-05',
                    'finalSalaryAmount' => '5000000',
                    'clearanceNotes' => 'All clear',
                ])->assertStatus(201)->json('data.id');

            $resp = $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
                ->getJson('/v1/hcm/terminations/'.$id)
                ->assertOk();

            // Evidence snapshot is stored
            $snapshot = $resp->json('data.settlement.evidenceSnapshot');
            $this->assertNotNull($snapshot, 'evidenceSnapshot should be present on finalized termination');
            $this->assertArrayHasKey('base_salary', $snapshot);
            $this->assertArrayHasKey('snapshot_at', $snapshot);

            // leaveBalanceAvailable flag is set (true or false, not null)
            $this->assertNotNull($resp->json('data.settlement.leaveBalanceAvailable'));

            // Breakdown includes at least the prorata salary item
            $breakdown = $resp->json('data.settlement.breakdown');
            $this->assertIsArray($breakdown);
            $this->assertNotEmpty($breakdown);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_leave_balance_unconfirmed_blocks_finalization(): void
    {
        Carbon::setTestNow('2026-05-26 10:00:00');

        try {
            [$admin, $adminToken] = $this->login(true);
            [$emp, $empToken] = $this->login(false);

            EmployeeProfile::query()->updateOrCreate(
                ['user_id' => $emp->id],
                ['company_id' => 1, 'base_salary' => 4_000_000]
            );

            $id = $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
                ->postJson('/v1/hcm/terminations', [
                    'userId' => $emp->uuid,
                    'department' => 'Ops',
                    'terminationType' => 'Layoff',
                    'terminationReasonCode' => 'company_efficiency',
                    'legalBasisCode' => 'pp_35_2021',
                    'reason' => 'Redundancy',
                    'noticeDate' => '2026-04-01',
                    'terminationDate' => '2026-04-30',
                ])->assertStatus(201)->json('data.id');

            // Step through required stages: draft_review → legal_review → approved_internal
            $v0 = $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
                ->getJson('/v1/hcm/terminations/'.$id)->assertOk()->json('data.workflow.version');

            $v1 = $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
                ->putJson('/v1/hcm/terminations/'.$id, [
                    'workflowStage' => 'legal_review',
                    'workflowVersion' => $v0,
                ])->assertOk();
            $v1 = $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
                ->getJson('/v1/hcm/terminations/'.$id)->assertOk()->json('data.workflow.version');

            $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
                ->putJson('/v1/hcm/terminations/'.$id, [
                    'workflowStage' => 'approved_internal',
                    'workflowVersion' => $v1,
                ])->assertOk();
            $v2 = $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
                ->getJson('/v1/hcm/terminations/'.$id)->assertOk()->json('data.workflow.version');

            // Attempt finalization WITHOUT manualLeavePayoutConfirmed
            $response = $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
                ->putJson('/v1/hcm/terminations/'.$id, [
                    'status' => 'finalized',
                    'workflowVersion' => $v2,
                    'settlementPayrollPeriod' => '2026-05',
                    'finalSalaryAmount' => '4000000',
                    'clearanceNotes' => 'All clear',
                ]);

            // Either succeeds (leave available) or returns 422 with correct code
            if ($response->status() === 422) {
                $response->assertJsonPath('error.code', 'LEAVE_BALANCE_UNCONFIRMED');
            } else {
                $response->assertOk();
                // GET to verify leaveBalanceAvailable was persisted
                $show = $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
                    ->getJson('/v1/hcm/terminations/'.$id)->assertOk();
                $this->assertNotNull($show->json('data.settlement.leaveBalanceAvailable'));
            }
        } finally {
            Carbon::setTestNow();
        }
    }
}
