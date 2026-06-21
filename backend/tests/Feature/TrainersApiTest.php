<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrainersApiTest extends TestCase
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

    public function test_trainers_admin_only_crud(): void
    {
        $admin = $this->login('qa.login@example.com', 'QA Super User');
        $employee = $this->login('employee@example.com', 'Employee');

        $hAdmin = ['Authorization' => 'Bearer '.$admin['token']];
        $hEmp = ['Authorization' => 'Bearer '.$employee['token']];

        // Employee forbidden list
        $this->withHeaders($hEmp)->getJson('/v1/hcm/training/trainers')
            ->assertStatus(403)
            ->assertJsonPath('success', false);

        // Admin create
        $create = $this->withHeaders($hAdmin)->postJson('/v1/hcm/training/trainers', [
            'name' => 'Trainer A',
            'email' => 'trainer.a@example.com',
            'phone' => '081234',
            'description' => 'desc',
            'isActive' => true,
        ])->assertStatus(201);

        $id = (int) $create->json('data.id');
        $this->assertGreaterThan(0, $id);

        // Admin list
        $this->withHeaders($hAdmin)->getJson('/v1/hcm/training/trainers?perPage=50')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['id' => $id, 'name' => 'Trainer A']);

        // Admin update
        $this->withHeaders($hAdmin)->putJson("/v1/hcm/training/trainers/{$id}", [
            'name' => 'Trainer A Updated',
            'email' => null,
            'phone' => null,
            'description' => null,
            'isActive' => false,
        ])->assertOk()->assertJsonPath('success', true);

        // Filter status
        $this->withHeaders($hAdmin)->getJson('/v1/hcm/training/trainers?status=inactive')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['id' => $id]);

        // Admin delete
        $this->withHeaders($hAdmin)->deleteJson("/v1/hcm/training/trainers/{$id}")
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_trainers_list_is_tenant_scoped(): void
    {
        $companyA = Company::query()->create([
            'code' => 'TRAINER_A',
            'name' => 'Trainer Company A',
            'domain' => 'trainer-a.local',
        ]);
        $companyB = Company::query()->create([
            'code' => 'TRAINER_B',
            'name' => 'Trainer Company B',
            'domain' => 'trainer-b.local',
        ]);

        $adminA = $this->createHcmAdminWithCompany([
            'name' => 'Trainer Admin A',
            'email' => 'trainer-admin-a@example.com',
        ], $companyA);
        $adminB = $this->createHcmAdminWithCompany([
            'name' => 'Trainer Admin B',
            'email' => 'trainer-admin-b@example.com',
        ], $companyB);

        $headersA = ['Authorization' => 'Bearer '.$adminA['token'], 'X-Company-Id' => (string) $companyA->id];
        $headersB = ['Authorization' => 'Bearer '.$adminB['token'], 'X-Company-Id' => (string) $companyB->id];

        $this->withHeaders($headersA)->postJson('/v1/hcm/training/trainers', [
            'name' => 'Tenant Trainer A',
            'email' => 'tenant-a@example.com',
            'isActive' => true,
        ])->assertStatus(201);

        $this->withHeaders($headersB)->postJson('/v1/hcm/training/trainers', [
            'name' => 'Tenant Trainer B',
            'email' => 'tenant-b@example.com',
            'isActive' => true,
        ])->assertStatus(201);

        $this->withHeaders($headersA)->getJson('/v1/hcm/training/trainers?perPage=50')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Tenant Trainer A'])
            ->assertJsonMissing(['name' => 'Tenant Trainer B']);
    }
}
