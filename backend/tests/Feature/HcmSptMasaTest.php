<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\EmployeeProfile;
use App\Models\EmployeeTaxProfile;
use App\Models\HcmPayrollLine;
use App\Models\HcmPayrollPeriod;
use App\Models\HcmPayrollRun;
use App\Models\HcmPermission;
use App\Models\HcmRolePermission;
use App\Models\HcmUserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Tests\TestCase;

#[IgnoreDeprecations]
class HcmSptMasaTest extends TestCase
{
    use RefreshDatabase;

    private const PERIODE = '2025-01';

    private const YEAR = 2025;

    private const MONTH = 1;

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Create an admin user with company and seed tax.spt.view + tax.spt.manage permissions.
     */
    private function makeAdmin(string $email): array
    {
        $result = $this->createHcmAdminWithCompany(['email' => $email]);
        $company = $result['company'];

        // Seed SPT permissions
        $this->seedSptPermissions($company);

        return $result;
    }

    private function seedSptPermissions(Company $company): void
    {
        $user = User::query()
            ->whereHas('companyMemberships', fn ($q) => $q->where('company_id', $company->id))
            ->first();

        $roleId = HcmUserRole::query()
            ->where('user_id', $user->id)
            ->where('company_id', $company->id)
            ->value('role_id');

        foreach ([
            'tax.spt.view' => ['module' => 'tax', 'resource' => 'spt', 'action' => 'view'],
            'tax.spt.manage' => ['module' => 'tax', 'resource' => 'spt', 'action' => 'manage'],
        ] as $code => $attrs) {
            $perm = HcmPermission::query()->firstOrCreate(['code' => $code], [
                'module' => $attrs['module'],
                'resource' => $attrs['resource'],
                'action' => $attrs['action'],
                'name' => $code,
                'is_active' => true,
            ]);
            HcmRolePermission::withoutTimestamps(function () use ($roleId, $perm, $company): void {
                HcmRolePermission::firstOrCreate([
                    'role_id' => $roleId,
                    'permission_id' => $perm->id,
                    'company_id' => $company->id,
                ]);
            });
        }
    }

    private function headers(string $token, int $companyId): array
    {
        return [
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $companyId,
        ];
    }

    /**
     * Create a finalized monthly payroll period + run + lines for the given company.
     */
    private function seedFinalizedPayroll(int $companyId, int $userId): HcmPayrollRun
    {
        $period = HcmPayrollPeriod::query()->create([
            'company_id' => $companyId,
            'period_year' => self::YEAR,
            'period_month' => self::MONTH,
            'status' => HcmPayrollPeriod::STATUS_POSTED,
        ]);

        $run = HcmPayrollRun::query()->create([
            'company_id' => $companyId,
            'hcm_payroll_period_id' => $period->id,
            'purpose' => HcmPayrollRun::PURPOSE_MONTHLY,
            'status' => HcmPayrollRun::STATUS_FINALIZED,
        ]);

        // Seed payroll lines for the user
        HcmPayrollLine::query()->create([
            'company_id' => $companyId,
            'hcm_payroll_run_id' => $run->id,
            'user_id' => $userId,
            'component_code' => 'GAJI_POKOK',
            'component_name' => 'Gaji Pokok',
            'kind' => 'addition',
            'category' => 'pph21_taxable_basic',
            'amount' => 10_000_000,
        ]);

        HcmPayrollLine::query()->create([
            'company_id' => $companyId,
            'hcm_payroll_run_id' => $run->id,
            'user_id' => $userId,
            'component_code' => 'PPH21',
            'component_name' => 'PPh 21',
            'kind' => 'deduction',
            'category' => 'pph21',
            'amount' => 500_000,
        ]);

        return $run;
    }

    /**
     * Seed EmployeeProfile + EmployeeTaxProfile so generation can map contract type.
     */
    private function seedEmployeeProfile(int $userId): void
    {
        EmployeeProfile::query()->firstOrCreate(
            ['user_id' => $userId],
            [
                'company_id' => 1,
                'contract_type' => 'permanent',
                'employee_id' => 'EMP-TEST-'.$userId,
            ]
        );

        EmployeeTaxProfile::query()->firstOrCreate(
            ['employee_id' => $userId],
            [
                'npwp' => '123456789012345',
                'nik' => '3174000000000001',
                'ptkp_status' => 'TK0',
                'effective_date' => '2025-01-01',
            ]
        );
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 401 / 403 guard
    // ──────────────────────────────────────────────────────────────────────────

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->getJson('/v1/hcm/spt-masa/headers');
        $response->assertStatus(401);
    }

    public function test_request_without_company_context_returns_403(): void
    {
        // A tenant-scoped HCM admin without X-Company-Id header has no resolvable
        // company context → hasPermission() falls back to isHcmAdmin() which returns
        // false for tenant admins → 403 before the 400 check is reached.
        $result = $this->makeAdmin('spt-nocompany@example.com');
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$result['token'],
        ])->getJson('/v1/hcm/spt-masa/headers');

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // List
    // ──────────────────────────────────────────────────────────────────────────

    public function test_list_returns_empty_when_no_headers(): void
    {
        $result = $this->makeAdmin('spt-list-empty@example.com');
        $response = $this->withHeaders($this->headers($result['token'], $result['company_id']))
            ->getJson('/v1/hcm/spt-masa/headers');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.meta.total', 0);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Generate — rejected if no finalized run
    // ──────────────────────────────────────────────────────────────────────────

    public function test_generate_rejected_when_no_finalized_run(): void
    {
        $result = $this->makeAdmin('spt-gen-nofinal@example.com');

        $response = $this->withHeaders($this->headers($result['token'], $result['company_id']))
            ->postJson('/v1/hcm/spt-masa/headers', ['periode' => self::PERIODE]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'SPT_PAYROLL_NOT_FINAL');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Happy path: generate → list shows header
    // ──────────────────────────────────────────────────────────────────────────

    public function test_generate_creates_header_when_finalized_run_exists(): void
    {
        $result = $this->makeAdmin('spt-gen-ok@example.com');
        $companyId = $result['company_id'];
        $user = User::query()->whereHas('companyMemberships', fn ($q) => $q->where('company_id', $companyId))->first();

        $this->seedFinalizedPayroll($companyId, $user->id);
        $this->seedEmployeeProfile($user->id);

        $response = $this->withHeaders($this->headers($result['token'], $companyId))
            ->postJson('/v1/hcm/spt-masa/headers', ['periode' => self::PERIODE]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.periode', self::PERIODE)
            ->assertJsonPath('data.status', 'draft');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Duplicate generate rejected
    // ──────────────────────────────────────────────────────────────────────────

    public function test_generate_duplicate_rejected(): void
    {
        $result = $this->makeAdmin('spt-gen-dup@example.com');
        $companyId = $result['company_id'];
        $user = User::query()->whereHas('companyMemberships', fn ($q) => $q->where('company_id', $companyId))->first();

        $this->seedFinalizedPayroll($companyId, $user->id);
        $this->seedEmployeeProfile($user->id);

        $headers = $this->headers($result['token'], $companyId);

        $this->withHeaders($headers)->postJson('/v1/hcm/spt-masa/headers', ['periode' => self::PERIODE])
            ->assertStatus(201);

        $second = $this->withHeaders($headers)->postJson('/v1/hcm/spt-masa/headers', ['periode' => self::PERIODE]);
        $second->assertStatus(409)
            ->assertJsonPath('error.code', 'SPT_HEADER_DUPLICATE');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Idempotency via generationKey
    // ──────────────────────────────────────────────────────────────────────────

    public function test_idempotent_generate_via_generation_key(): void
    {
        $result = $this->makeAdmin('spt-gen-idempotent@example.com');
        $companyId = $result['company_id'];
        $user = User::query()->whereHas('companyMemberships', fn ($q) => $q->where('company_id', $companyId))->first();

        $this->seedFinalizedPayroll($companyId, $user->id);
        $this->seedEmployeeProfile($user->id);

        $headers = $this->headers($result['token'], $companyId);
        $key = 'idempotent-key-001';

        $first = $this->withHeaders($headers)->postJson('/v1/hcm/spt-masa/headers', [
            'periode' => self::PERIODE,
            'generationKey' => $key,
        ])->assertStatus(201)->json('data');

        // Second call with same key returns 200 and same uuid.
        $second = $this->withHeaders($headers)->postJson('/v1/hcm/spt-masa/headers', [
            'periode' => self::PERIODE,
            'generationKey' => $key,
        ])->assertOk()->json('data');

        $this->assertSame($first['uuid'], $second['uuid']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Show
    // ──────────────────────────────────────────────────────────────────────────

    public function test_show_returns_header_with_details(): void
    {
        $result = $this->makeAdmin('spt-show@example.com');
        $companyId = $result['company_id'];
        $user = User::query()->whereHas('companyMemberships', fn ($q) => $q->where('company_id', $companyId))->first();

        $this->seedFinalizedPayroll($companyId, $user->id);
        $this->seedEmployeeProfile($user->id);

        $headers = $this->headers($result['token'], $companyId);

        $uuid = $this->withHeaders($headers)->postJson('/v1/hcm/spt-masa/headers', ['periode' => self::PERIODE])
            ->assertStatus(201)->json('data.uuid');

        $this->withHeaders($headers)->getJson('/v1/hcm/spt-masa/headers/'.$uuid)
            ->assertOk()
            ->assertJsonPath('data.uuid', $uuid)
            ->assertJsonStructure(['data' => ['details']]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Mark Ready → Submit happy path
    // ──────────────────────────────────────────────────────────────────────────

    public function test_happy_path_generate_mark_ready_submit(): void
    {
        $result = $this->makeAdmin('spt-happy@example.com');
        $companyId = $result['company_id'];
        $user = User::query()->whereHas('companyMemberships', fn ($q) => $q->where('company_id', $companyId))->first();

        $this->seedFinalizedPayroll($companyId, $user->id);
        $this->seedEmployeeProfile($user->id);

        $headers = $this->headers($result['token'], $companyId);

        // Generate
        $data = $this->withHeaders($headers)->postJson('/v1/hcm/spt-masa/headers', ['periode' => self::PERIODE])
            ->assertStatus(201)->json('data');

        $uuid = $data['uuid'];
        $version = $data['version'];
        $this->assertSame('draft', $data['status']);

        // Mark Ready
        $data = $this->withHeaders($headers)->postJson("/v1/hcm/spt-masa/headers/{$uuid}/mark-ready", [
            'version' => $version,
        ])->assertOk()->json('data');

        $this->assertSame('ready', $data['status']);
        $version = $data['version'];

        // Submit
        $data = $this->withHeaders($headers)->postJson("/v1/hcm/spt-masa/headers/{$uuid}/submit", [
            'version' => $version,
            'notes' => 'Sudah setor via DJP Online.',
        ])->assertOk()->json('data');

        $this->assertSame('submitted', $data['status']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Version conflict
    // ──────────────────────────────────────────────────────────────────────────

    public function test_version_conflict_returns_409(): void
    {
        $result = $this->makeAdmin('spt-vconflict@example.com');
        $companyId = $result['company_id'];
        $user = User::query()->whereHas('companyMemberships', fn ($q) => $q->where('company_id', $companyId))->first();

        $this->seedFinalizedPayroll($companyId, $user->id);
        $this->seedEmployeeProfile($user->id);

        $headers = $this->headers($result['token'], $companyId);

        $data = $this->withHeaders($headers)->postJson('/v1/hcm/spt-masa/headers', ['periode' => self::PERIODE])
            ->assertStatus(201)->json('data');

        $uuid = $data['uuid'];

        // Send wrong version (stale)
        $this->withHeaders($headers)->postJson("/v1/hcm/spt-masa/headers/{$uuid}/mark-ready", [
            'version' => 999,
        ])->assertStatus(409)
            ->assertJsonPath('error.code', 'SPT_VERSION_CONFLICT');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Invalid transition: submit from draft
    // ──────────────────────────────────────────────────────────────────────────

    public function test_submit_from_draft_rejected(): void
    {
        $result = $this->makeAdmin('spt-invalid-transition@example.com');
        $companyId = $result['company_id'];
        $user = User::query()->whereHas('companyMemberships', fn ($q) => $q->where('company_id', $companyId))->first();

        $this->seedFinalizedPayroll($companyId, $user->id);
        $this->seedEmployeeProfile($user->id);

        $headers = $this->headers($result['token'], $companyId);

        $data = $this->withHeaders($headers)->postJson('/v1/hcm/spt-masa/headers', ['periode' => self::PERIODE])
            ->assertStatus(201)->json('data');

        // Submit directly from draft (not allowed)
        $this->withHeaders($headers)->postJson("/v1/hcm/spt-masa/headers/{$data['uuid']}/submit", [
            'version' => $data['version'],
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'SPT_INVALID_TRANSITION');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Regenerate on submitted rejected
    // ──────────────────────────────────────────────────────────────────────────

    public function test_regenerate_on_submitted_rejected(): void
    {
        $result = $this->makeAdmin('spt-regen-submitted@example.com');
        $companyId = $result['company_id'];
        $user = User::query()->whereHas('companyMemberships', fn ($q) => $q->where('company_id', $companyId))->first();

        $this->seedFinalizedPayroll($companyId, $user->id);
        $this->seedEmployeeProfile($user->id);

        $headers = $this->headers($result['token'], $companyId);

        // Full flow to submitted
        $data = $this->withHeaders($headers)->postJson('/v1/hcm/spt-masa/headers', ['periode' => self::PERIODE])
            ->assertStatus(201)->json('data');

        $data = $this->withHeaders($headers)->postJson("/v1/hcm/spt-masa/headers/{$data['uuid']}/mark-ready", [
            'version' => $data['version'],
        ])->assertOk()->json('data');

        $data = $this->withHeaders($headers)->postJson("/v1/hcm/spt-masa/headers/{$data['uuid']}/submit", [
            'version' => $data['version'],
        ])->assertOk()->json('data');

        // Try regenerate on submitted
        $this->withHeaders($headers)->postJson("/v1/hcm/spt-masa/headers/{$data['uuid']}/regenerate", [
            'version' => $data['version'],
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'SPT_INVALID_TRANSITION');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Tenant isolation
    // ──────────────────────────────────────────────────────────────────────────

    public function test_tenant_a_cannot_see_tenant_b_headers(): void
    {
        // Tenant A
        $a = $this->makeAdmin('spt-tenant-a@example.com');
        $userA = User::query()->whereHas('companyMemberships', fn ($q) => $q->where('company_id', $a['company_id']))->first();
        $this->seedFinalizedPayroll($a['company_id'], $userA->id);
        $this->seedEmployeeProfile($userA->id);

        $headersA = $this->headers($a['token'], $a['company_id']);
        $uuidA = $this->withHeaders($headersA)->postJson('/v1/hcm/spt-masa/headers', ['periode' => self::PERIODE])
            ->assertStatus(201)->json('data.uuid');

        // Tenant B
        $b = $this->makeAdmin('spt-tenant-b@example.com');
        $headersB = $this->headers($b['token'], $b['company_id']);

        // Tenant B tries to access tenant A's header
        $this->withHeaders($headersB)->getJson('/v1/hcm/spt-masa/headers/'.$uuidA)
            ->assertStatus(404);

        // Tenant B list should be empty
        $this->withHeaders($headersB)->getJson('/v1/hcm/spt-masa/headers')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 0);
    }
}
