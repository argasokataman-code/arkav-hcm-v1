<?php

namespace Tests\Feature;

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
}

