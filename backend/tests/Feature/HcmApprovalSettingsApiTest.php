<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\HcmApprovalConfig;
use App\Models\HcmApprovalConfigApprover;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HcmApprovalSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    private const BASE_URL = '/v1/hcm/approval-settings';

    // ===== HELPERS =====

    private function authHeaders(array $auth): array
    {
        return $this->withCompanyContext(
            ['Authorization' => 'Bearer '.$auth['token']],
            $auth['company_id']
        );
    }

    private function apiGet(string $uri, array $auth, array $query = []): \Illuminate\Testing\TestResponse
    {
        return $this->getJson($uri . (empty($query) ? '' : '?' . http_build_query($query)),
            $this->authHeaders($auth)
        );
    }

    private function apiPut(string $uri, array $auth, array $data): \Illuminate\Testing\TestResponse
    {
        return $this->putJson($uri, $data, $this->authHeaders($auth));
    }

    private function createNonAdminUser(): array
    {
        $company = $this->createIsolatedTestCompany();

        $user = User::query()->create([
            'name' => 'Non Admin',
            'email' => 'nonadmin-'.uniqid().'@example.com',
            'password' => bcrypt('StrongPass1'),
        ]);

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        // Register
        $this->postJson('/v1/identity/auth/register', [
            'name' => $user->name,
            'email' => $user->email,
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ]);

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => $user->email,
            'password' => 'StrongPass1',
            'companyCode' => $company->code,
        ])->assertOk();

        return [
            'token' => (string) $login->json('data.accessToken'),
            'company_id' => $company->id,
            'company' => $company,
        ];
    }

    private function createApproverUser(int $companyId, string $suffix = ''): User
    {
        $u = User::query()->create([
            'name' => 'Approver'.$suffix,
            'email' => 'approver'.$suffix.uniqid().'@test.com',
            'password' => bcrypt('password'),
        ]);
        CompanyUser::query()->create([
            'company_id' => $companyId,
            'user_id' => $u->id,
            'role' => 'admin',
            'status' => 'active',
        ]);
        return $u;
    }

    // ======================================
    // 2.1 — GET /v1/hcm/approval-settings
    // ======================================

    public function test_get_settings_returns_401_without_token(): void
    {
        $this->getJson(self::BASE_URL)->assertStatus(401);
    }

    public function test_get_settings_returns_403_for_non_admin(): void
    {
        $auth = $this->createNonAdminUser();
        $this->apiGet(self::BASE_URL, $auth)->assertStatus(403);
    }

    public function test_get_settings_returns_empty_configs_for_new_company(): void
    {
        $auth = $this->createHcmAdminWithCompany();
        $response = $this->apiGet(self::BASE_URL, $auth);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $data = $response->json('data');

        $this->assertArrayHasKey('leave', $data);
        $this->assertArrayHasKey('overtime', $data);
        $this->assertArrayHasKey('resignation', $data);
        $this->assertArrayHasKey('termination', $data);
        $this->assertArrayHasKey('expense', $data);
        $this->assertArrayHasKey('offer', $data);

        foreach (['leave', 'overtime', 'resignation', 'termination'] as $mod) {
            $this->assertFalse($data[$mod]['isActive'], "Module {$mod} should be inactive by default");
            $this->assertSame('simultaneous', $data[$mod]['approvalMode']);
            $this->assertEmpty($data[$mod]['approvers']);
        }
    }

    public function test_get_settings_returns_saved_config_after_upsert(): void
    {
        $auth = $this->createHcmAdminWithCompany();
        $approver = $this->createApproverUser($auth['company_id'], 'GetSaved');

        $this->apiPut(self::BASE_URL.'/leave', $auth, [
            'approvalMode' => 'sequence',
            'approverUserIds' => [$approver->id],
        ])->assertOk();

        $response = $this->apiGet(self::BASE_URL, $auth);
        $response->assertOk();
        $leave = $response->json('data.leave');

        $this->assertTrue($leave['isActive']);
        $this->assertSame('sequence', $leave['approvalMode']);
        $this->assertCount(1, $leave['approvers']);
        $this->assertSame($approver->id, $leave['approvers'][0]['userId']);
    }

    // ======================================
    // 2.2 — PUT /v1/hcm/approval-settings/{module}
    // ======================================

    public function test_put_creates_new_config(): void
    {
        $auth = $this->createHcmAdminWithCompany();
        $approver = $this->createApproverUser($auth['company_id'], 'Create');

        $response = $this->apiPut(self::BASE_URL.'/leave', $auth, [
            'approvalMode' => 'sequence',
            'approverUserIds' => [$approver->id],
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $data = $response->json('data');

        $this->assertSame('leave', $data['module']);
        $this->assertSame('sequence', $data['approvalMode']);
        $this->assertTrue($data['isActive']);
        $this->assertCount(1, $data['approvers']);

        $this->assertDatabaseHas('hcm_approval_configs', [
            'company_id' => $auth['company_id'],
            'module' => 'leave',
            'approval_mode' => 'sequence',
            'is_active' => true,
        ]);
    }

    public function test_put_updates_existing_config(): void
    {
        $auth = $this->createHcmAdminWithCompany();
        $approver1 = $this->createApproverUser($auth['company_id'], 'Upd1');
        $approver2 = $this->createApproverUser($auth['company_id'], 'Upd2');

        $this->apiPut(self::BASE_URL.'/leave', $auth, [
            'approvalMode' => 'sequence',
            'approverUserIds' => [$approver1->id],
        ])->assertOk();

        $response = $this->apiPut(self::BASE_URL.'/leave', $auth, [
            'approvalMode' => 'simultaneous',
            'approverUserIds' => [$approver2->id],
        ]);
        $response->assertOk();

        $data = $response->json('data');
        $this->assertSame('simultaneous', $data['approvalMode']);
        $this->assertCount(1, $data['approvers']);
        $this->assertSame($approver2->id, $data['approvers'][0]['userId']);

        $oldCount = HcmApprovalConfigApprover::query()
            ->where('approver_user_id', $approver1->id)
            ->count();
        $this->assertSame(0, $oldCount, 'Old approver should be removed');
    }

    public function test_put_returns_422_for_invalid_module(): void
    {
        $auth = $this->createHcmAdminWithCompany();
        // Module 'unsupportedmodule' passes route regex [a-z_]+ but is not in SUPPORTED_MODULES
        $this->apiPut(self::BASE_URL.'/unsupportedmodule', $auth, [
            'approvalMode' => 'sequence',
            'approverUserIds' => [1],
        ])->assertStatus(422);
    }

    public function test_put_returns_422_for_empty_approvers(): void
    {
        $auth = $this->createHcmAdminWithCompany();
        $this->apiPut(self::BASE_URL.'/leave', $auth, [
            'approvalMode' => 'sequence',
            'approverUserIds' => [],
        ])->assertStatus(422);
    }

    public function test_put_returns_422_for_too_many_approvers(): void
    {
        $auth = $this->createHcmAdminWithCompany();
        $this->apiPut(self::BASE_URL.'/leave', $auth, [
            'approvalMode' => 'sequence',
            'approverUserIds' => range(1, 11),
        ])->assertStatus(422);
    }

    public function test_put_returns_422_for_non_existent_user_ids(): void
    {
        $auth = $this->createHcmAdminWithCompany();
        $this->apiPut(self::BASE_URL.'/leave', $auth, [
            'approvalMode' => 'sequence',
            'approverUserIds' => [999999],
        ])->assertStatus(422);
    }

    public function test_put_rejects_approver_not_in_company_tenant_isolation(): void
    {
        $auth = $this->createHcmAdminWithCompany();
        $otherCompany = $this->createIsolatedTestCompany();

        $outsider = User::query()->create([
            'name' => 'Outsider',
            'email' => 'outsider-'.uniqid().'@other.com',
            'password' => bcrypt('password'),
        ]);
        CompanyUser::query()->create([
            'company_id' => $otherCompany->id,
            'user_id' => $outsider->id,
            'role' => 'admin',
            'status' => 'active',
        ]);

        $response = $this->apiPut(self::BASE_URL.'/leave', $auth, [
            'approvalMode' => 'sequence',
            'approverUserIds' => [$outsider->id],
        ]);
        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'APPROVER_NOT_IN_COMPANY');
    }

    public function test_put_returns_422_for_invalid_approval_mode(): void
    {
        $auth = $this->createHcmAdminWithCompany();
        $approver = $this->createApproverUser($auth['company_id'], 'InvMode');

        $this->apiPut(self::BASE_URL.'/leave', $auth, [
            'approvalMode' => 'invalid_mode',
            'approverUserIds' => [$approver->id],
        ])->assertStatus(422);
    }

    public function test_put_returns_403_for_non_admin(): void
    {
        $auth = $this->createNonAdminUser();
        $this->apiPut(self::BASE_URL.'/leave', $auth, [
            'approvalMode' => 'sequence',
            'approverUserIds' => [1],
        ])->assertStatus(403);
    }

    public function test_put_saves_sequence_mode(): void
    {
        $auth = $this->createHcmAdminWithCompany();
        $approver = $this->createApproverUser($auth['company_id'], 'Seq');

        $response = $this->apiPut(self::BASE_URL.'/leave', $auth, [
            'approvalMode' => 'sequence',
            'approverUserIds' => [$approver->id],
        ]);
        $response->assertOk();
        $this->assertSame('sequence', $response->json('data.approvalMode'));
    }

    public function test_put_saves_simultaneous_mode(): void
    {
        $auth = $this->createHcmAdminWithCompany();
        $approver = $this->createApproverUser($auth['company_id'], 'Simul');

        $response = $this->apiPut(self::BASE_URL.'/leave', $auth, [
            'approvalMode' => 'simultaneous',
            'approverUserIds' => [$approver->id],
        ]);
        $response->assertOk();
        $this->assertSame('simultaneous', $response->json('data.approvalMode'));
    }

    // ======================================
    // 2.3 — GET /v1/hcm/approval-settings/eligible-approvers
    // ======================================

    public function test_eligible_approvers_returns_company_members(): void
    {
        $auth = $this->createHcmAdminWithCompany();

        $response = $this->apiGet(self::BASE_URL.'/eligible-approvers', $auth);
        $response->assertOk();
        $response->assertJson(['success' => true]);

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertGreaterThanOrEqual(1, count($data));
    }

    public function test_eligible_approvers_search_by_name_works(): void
    {
        $auth = $this->createHcmAdminWithCompany();

        $target = User::query()->create([
            'name' => 'Zorro Approver',
            'email' => 'zorro-'.uniqid().'@test.com',
            'password' => bcrypt('password'),
        ]);
        CompanyUser::query()->create([
            'company_id' => $auth['company_id'],
            'user_id' => $target->id,
            'role' => 'admin',
            'status' => 'active',
        ]);

        $response = $this->apiGet(self::BASE_URL.'/eligible-approvers', $auth, ['q' => 'Zorro']);
        $response->assertOk();
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertSame('Zorro Approver', $data[0]['name']);
    }

    public function test_eligible_approvers_returns_403_for_non_admin(): void
    {
        $auth = $this->createNonAdminUser();
        $this->apiGet(self::BASE_URL.'/eligible-approvers', $auth)->assertStatus(403);
    }
}
