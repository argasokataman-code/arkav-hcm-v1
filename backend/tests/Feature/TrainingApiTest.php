<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $tRes = $this->withHeaders($hAdmin)->postJson('/v1/hcm/training/trainings', [
                'trainingTypeId' => $typeId,
                'trainerName' => 'Trainer A',
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
}

