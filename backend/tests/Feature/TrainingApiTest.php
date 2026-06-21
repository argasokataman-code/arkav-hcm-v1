<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\HcmPermission;
use App\Models\HcmRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class TrainingApiTest extends TestCase
{
    use RefreshDatabase;

    private function login(string $email, string $name): array
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => $name,
            'email' => $email,
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $user = User::query()->where('email', $email)->firstOrFail();

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => $email,
            'password' => 'StrongPass1',
        ])->assertOk();

        return [
            'user' => $user,
            'token' => (string) $login->json('data.accessToken'),
        ];
    }

    public function test_training_type_admin_crud_and_employee_forbidden_mutation(): void
    {
        $admin = $this->login('qa.login@example.com', 'QA Super User');
        $employee = $this->login('employee@example.com', 'Training Employee');

        $hAdmin = ['Authorization' => 'Bearer '.$admin['token']];
        $hEmp = ['Authorization' => 'Bearer '.$employee['token']];

        // Employee can list (only active; empty ok)
        $this->withHeaders($hEmp)->getJson('/v1/hcm/training/types')
            ->assertOk()
            ->assertJsonPath('success', true);

        // Employee cannot create
        $this->withHeaders($hEmp)->postJson('/v1/hcm/training/types', ['name' => 'Git Training', 'isActive' => true])
            ->assertStatus(403)
            ->assertJsonPath('success', false);

        // Admin create
        $res = $this->withHeaders($hAdmin)->postJson('/v1/hcm/training/types', [
            'name' => 'Git Training',
            'description' => 'desc',
            'isActive' => true,
        ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $id = (int) ($res->json('data.id') ?? 0);
        $this->assertGreaterThan(0, $id);

        // Admin update
        $this->withHeaders($hAdmin)->putJson("/v1/hcm/training/types/{$id}", [
            'name' => 'Git Training Updated',
            'description' => 'desc2',
            'isActive' => false,
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        // Admin list includes inactive
        $this->withHeaders($hAdmin)->getJson('/v1/hcm/training/types')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['id' => $id, 'name' => 'Git Training Updated']);

        // Employee list should hide inactive
        $this->withHeaders($hEmp)->getJson('/v1/hcm/training/types')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonMissing(['id' => $id]);

        // Admin delete
        $this->withHeaders($hAdmin)->deleteJson("/v1/hcm/training/types/{$id}")
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_trainings_admin_only_crud(): void
    {
        $admin = $this->login('qa.login@example.com', 'QA Super User');
        $employee = $this->login('employee@example.com', 'Training Employee');

        $hAdmin = ['Authorization' => 'Bearer '.$admin['token']];
        $hEmp = ['Authorization' => 'Bearer '.$employee['token']];

        // Employee forbidden list
        $this->withHeaders($hEmp)->getJson('/v1/hcm/training/trainings')
            ->assertStatus(403)
            ->assertJsonPath('success', false);

        // Admin can create type + training
        $typeRes = $this->withHeaders($hAdmin)->postJson('/v1/hcm/training/types', [
            'name' => 'Development',
            'isActive' => true,
        ])
            ->assertStatus(201);
        $typeId = (int) $typeRes->json('data.id');

        $trainerRes = $this->withHeaders($hAdmin)->postJson('/v1/hcm/training/trainers', [
            'name' => 'Trainer A',
            'email' => 'trainer.a@example.com',
            'isActive' => true,
        ])->assertStatus(201);
        $trainerId = (int) $trainerRes->json('data.id');

        $tRes = $this->withHeaders($hAdmin)->postJson('/v1/hcm/training/trainings', [
            'trainingTypeId' => $typeId,
            'trainerId' => $trainerId,
            'participantUserIds' => [$employee['user']->id],
            'startDate' => '2026-04-09',
            'endDate' => '2026-04-10',
            'description' => 'desc',
            'costCents' => 250000,
            'status' => 'active',
        ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);
        $trainingId = (int) $tRes->json('data.id');
        $this->assertGreaterThan(0, $trainingId);

        // Admin list returns data
        $this->withHeaders($hAdmin)->getJson('/v1/hcm/training/trainings?perPage=50')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['id' => $trainingId, 'trainerName' => 'Trainer A']);

        $this->assertDatabaseHas('hcm_trainings', [
            'id' => $trainingId,
            'trainer_id' => $trainerId,
            'trainer_name' => 'Trainer A',
        ]);

        // Admin update
        $this->withHeaders($hAdmin)->putJson("/v1/hcm/training/trainings/{$trainingId}", [
            'status' => 'completed',
            'participantUserIds' => [],
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        // Admin delete
        $this->withHeaders($hAdmin)->deleteJson("/v1/hcm/training/trainings/{$trainingId}")
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_trainings_for_user_allows_admin_any_and_employee_self_only(): void
    {
        $admin = $this->login('qa.login@example.com', 'QA Super User');
        $employee = $this->login('employee@example.com', 'Training Employee');
        $other = $this->login('other@example.com', 'Other Employee');

        $hAdmin = ['Authorization' => 'Bearer '.$admin['token']];
        $hEmp = ['Authorization' => 'Bearer '.$employee['token']];

        // Seed a type + training assigned to employee
        $typeRes = $this->withHeaders($hAdmin)->postJson('/v1/hcm/training/types', [
            'name' => 'Development',
            'isActive' => true,
        ])->assertStatus(201);
        $typeId = (int) $typeRes->json('data.id');

        $tRes = $this->withHeaders($hAdmin)->postJson('/v1/hcm/training/trainings', [
            'trainingTypeId' => $typeId,
            'trainerId' => null,
            'trainerName' => 'Trainer A',
            'participantUserIds' => [$employee['user']->id],
            'startDate' => '2026-04-09',
            'endDate' => '2026-04-10',
            'description' => 'desc',
            'costCents' => 0,
            'status' => 'active',
        ])->assertStatus(201);
        $trainingId = (int) $tRes->json('data.id');

        // Employee can read self trainings
        $this->withHeaders($hEmp)->getJson('/v1/hcm/training/users/'.$employee['user']->id.'/trainings')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['id' => $trainingId]);

        // Employee cannot read others
        $this->withHeaders($hEmp)->getJson('/v1/hcm/training/users/'.$other['user']->id.'/trainings')
            ->assertStatus(403)
            ->assertJsonPath('success', false);

        // Admin can read anyone
        $this->withHeaders($hAdmin)->getJson('/v1/hcm/training/users/'.$employee['user']->id.'/trainings')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['id' => $trainingId]);
    }

    public function test_training_types_and_trainings_are_tenant_scoped_for_admins(): void
    {
        $companyA = Company::query()->create([
            'code' => 'TRAIN_A',
            'name' => 'Training Company A',
            'domain' => 'train-a.local',
        ]);
        $companyB = Company::query()->create([
            'code' => 'TRAIN_B',
            'name' => 'Training Company B',
            'domain' => 'train-b.local',
        ]);

        $adminA = $this->createHcmAdminWithCompany([
            'name' => 'Training Admin A',
            'email' => 'training-admin-a@example.com',
        ], $companyA);
        $adminB = $this->createHcmAdminWithCompany([
            'name' => 'Training Admin B',
            'email' => 'training-admin-b@example.com',
        ], $companyB);

        $headersA = [
            'Authorization' => 'Bearer '.$adminA['token'],
            'X-Company-Id' => (string) $companyA->id,
        ];
        $headersB = [
            'Authorization' => 'Bearer '.$adminB['token'],
            'X-Company-Id' => (string) $companyB->id,
        ];

        $typeA = $this->withHeaders($headersA)->postJson('/v1/hcm/training/types', [
            'name' => 'Type A',
            'isActive' => true,
        ])->assertStatus(201);
        $typeB = $this->withHeaders($headersB)->postJson('/v1/hcm/training/types', [
            'name' => 'Type B',
            'isActive' => true,
        ])->assertStatus(201);

        $trainerA = $this->withHeaders($headersA)->postJson('/v1/hcm/training/trainers', [
            'name' => 'Trainer A',
            'email' => 'trainer-a@example.com',
            'isActive' => true,
        ])->assertStatus(201);
        $trainerB = $this->withHeaders($headersB)->postJson('/v1/hcm/training/trainers', [
            'name' => 'Trainer B',
            'email' => 'trainer-b@example.com',
            'isActive' => true,
        ])->assertStatus(201);

        $this->withHeaders($headersA)->postJson('/v1/hcm/training/trainings', [
            'trainingTypeId' => (int) $typeA->json('data.id'),
            'trainerId' => (int) $trainerA->json('data.id'),
            'participantUserIds' => [],
            'startDate' => '2026-04-09',
            'endDate' => '2026-04-10',
            'description' => 'A only',
            'costCents' => 1000,
            'status' => 'active',
        ])->assertStatus(201);

        $this->withHeaders($headersB)->postJson('/v1/hcm/training/trainings', [
            'trainingTypeId' => (int) $typeB->json('data.id'),
            'trainerId' => (int) $trainerB->json('data.id'),
            'participantUserIds' => [],
            'startDate' => '2026-04-11',
            'endDate' => '2026-04-12',
            'description' => 'B only',
            'costCents' => 1000,
            'status' => 'active',
        ])->assertStatus(201);

        $this->withHeaders($headersA)->getJson('/v1/hcm/training/types')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Type A'])
            ->assertJsonMissing(['name' => 'Type B']);

        $this->withHeaders($headersA)->getJson('/v1/hcm/training/trainers')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Trainer A'])
            ->assertJsonMissing(['name' => 'Trainer B']);

        $this->withHeaders($headersA)->getJson('/v1/hcm/training/trainings?perPage=50')
            ->assertOk()
            ->assertJsonFragment(['description' => 'A only'])
            ->assertJsonMissing(['description' => 'B only']);
    }

    public function test_training_mutation_requires_manage_permission_not_view_only(): void
    {
        $company = Company::query()->create([
            'code' => 'TRAIN_VIEW_ONLY',
            'name' => 'Training View Only',
            'domain' => 'train-view-only.local',
        ]);

        $user = $this->login('training-view-only@example.com', 'Training View Only User');
        $authUser = User::query()->where('email', 'training-view-only@example.com')->firstOrFail();

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $authUser->id,
            'role' => 'member',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $role = HcmRole::query()->create([
            'company_id' => $company->id,
            'code' => 'TRAINING_VIEWER',
            'name' => 'Training Viewer',
            'status' => 'active',
            'is_system' => false,
        ]);
        $permission = HcmPermission::query()->firstOrCreate(
            ['code' => 'training.view'],
            ['module' => 'training', 'resource' => 'training', 'action' => 'view', 'name' => 'Training View', 'is_active' => true]
        );
        DB::table('hcm_role_permissions')->insert([
            'company_id' => $company->id,
            'role_id' => $role->id,
            'permission_id' => $permission->id,
            'uuid' => (string) Str::uuid(),
        ]);
        DB::table('hcm_user_roles')->insert([
            'user_id' => $authUser->id,
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
            'uuid' => (string) Str::uuid(),
        ]);

        $headers = [
            'Authorization' => 'Bearer '.$user['token'],
            'X-Company-Id' => (string) $company->id,
        ];

        $this->withHeaders($headers)->postJson('/v1/hcm/training/types', [
            'name' => 'Should be forbidden',
            'isActive' => true,
        ])->assertStatus(403);

        $this->withHeaders($headers)->getJson('/v1/hcm/training/types')
            ->assertOk()
            ->assertJsonPath('success', true);
    }
}
