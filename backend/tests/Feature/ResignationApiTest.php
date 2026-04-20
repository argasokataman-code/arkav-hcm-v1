<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResignationApiTest extends TestCase
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

    public function test_resignations_admin_crud_and_employee_forbidden(): void
    {
        [$admin, $adminToken] = $this->login(true);
        [$emp, $empToken] = $this->login(false);

        $this->withHeaders(['Authorization' => 'Bearer '.$empToken])
            ->getJson('/v1/hcm/resignations')
            ->assertStatus(403);

        $create = $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->postJson('/v1/hcm/resignations', [
                'userId' => $emp->uuid,
                'department' => 'Finance',
                'reason' => 'Career change',
                'noticeDate' => '2026-04-01',
                'resignationDate' => '2026-04-30',
                'notes' => 'OK',
            ])->assertStatus(201);

        $id = $create->json('data.id');
        $this->assertIsInt($id);

        $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->getJson('/v1/hcm/resignations')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->putJson('/v1/hcm/resignations/'.$id, [
                'status' => 'approved',
            ])->assertOk()
            ->assertJsonPath('success', true);

        $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->deleteJson('/v1/hcm/resignations/'.$id)
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_resignation_show_and_per_user_list_self_only(): void
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
            'reason' => 'Relocation',
            'noticeDate' => '2026-04-01',
            'resignationDate' => '2026-05-01',
            'notes' => 'ok',
        ];

        $idA = $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->postJson('/v1/hcm/resignations', array_merge(['userId' => $empA->uuid], $body))
            ->assertStatus(201)
            ->json('data.id');

        $idB = $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->postJson('/v1/hcm/resignations', array_merge(['userId' => $empB->uuid], $body))
            ->assertStatus(201)
            ->json('data.id');

        $this->withHeaders(['Authorization' => 'Bearer '.$empAToken])
            ->getJson('/v1/hcm/resignations/'.$idA)
            ->assertOk()
            ->assertJsonPath('success', true);

        $rowAUuid = (string) \App\Models\HcmResignation::query()->findOrFail($idA)->uuid;

        $this->withHeaders(['Authorization' => 'Bearer '.$empAToken])
            ->getJson('/v1/hcm/resignations/'.$rowAUuid)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.uuid', $rowAUuid)
            ->assertJsonPath('data.employee.uuid', $empA->uuid);

        $this->withHeaders(['Authorization' => 'Bearer '.$empAToken])
            ->getJson('/v1/hcm/resignations/'.$idB)
            ->assertStatus(403);

        $this->withHeaders(['Authorization' => 'Bearer '.$empAToken])
            ->getJson('/v1/hcm/resignations/users/'.$empA->id.'/resignations')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->withHeaders(['Authorization' => 'Bearer '.$empAToken])
            ->getJson('/v1/hcm/resignations/users/'.$empA->uuid.'/resignations')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->withHeaders(['Authorization' => 'Bearer '.$empAToken])
            ->getJson('/v1/hcm/resignations/users/'.$empB->id.'/resignations')
            ->assertStatus(403);
    }

    public function test_resignation_show_returns_404_when_not_found(): void
    {
        [$admin, $adminToken] = $this->login(true);

        $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->getJson('/v1/hcm/resignations/999999')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'RESIGNATION_NOT_FOUND');
    }

    public function test_resignation_create_rejects_user_uuid_outside_active_company(): void
    {
        [$admin, $adminToken] = $this->login(true);

        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Other Company Employee',
            'email' => 'other-company-employee@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $outsider = User::query()->where('email', 'other-company-employee@example.com')->firstOrFail();
        $activeCompanyId = (int) CompanyUser::query()->where('user_id', $admin->id)->value('company_id');
        $this->assertGreaterThan(0, $activeCompanyId);

        CompanyUser::query()->where('user_id', $outsider->id)->delete();

        $otherCompany = Company::query()->create([
            'code' => 'resign_other_company',
            'name' => 'Resign Other Company',
            'legal_name' => 'Resign Other Company PT',
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        CompanyUser::query()->create([
            'company_id' => $otherCompany->id,
            'user_id' => $outsider->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$adminToken,
            'X-Company-Id' => (string) $activeCompanyId,
        ])->postJson('/v1/hcm/resignations', [
            'userId' => $outsider->uuid,
            'department' => 'Finance',
            'reason' => 'Cross tenant injection',
            'noticeDate' => '2026-04-01',
            'resignationDate' => '2026-04-30',
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.message', 'The selected user id is invalid for the active company.');
    }
}
