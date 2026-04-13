<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionApiTest extends TestCase
{
    use RefreshDatabase;

    private function login(bool $asAdmin): array
    {
        // NOTE: Current Phase-1 admin heuristic lives in User::isHcmAdmin().
        // Use the dedicated QA admin login email to make tests deterministic.
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

    private function adminToken(): string
    {
        [, $token] = $this->login(true);

        return $token;
    }

    public function test_promotions_admin_crud_and_employee_forbidden(): void
    {
        [$admin, $adminToken] = $this->login(true);
        [$emp, $empToken] = $this->login(false);

        // Employee forbidden
        $this->withHeaders(['Authorization' => 'Bearer '.$empToken])
            ->getJson('/v1/hcm/promotions')
            ->assertStatus(403);

        // Admin create
        $create = $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->postJson('/v1/hcm/promotions', [
                'userId' => $emp->id,
                'department' => 'Finance',
                'designationFrom' => 'Accountant',
                'designationTo' => 'Sr Accountant',
                'promotionDate' => '2026-04-09',
                'notes' => 'Congrats',
            ])->assertStatus(201);

        $id = $create->json('data.id');
        $this->assertIsInt($id);

        // Admin list
        $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->getJson('/v1/hcm/promotions')
            ->assertOk()
            ->assertJsonPath('success', true);

        // Admin update
        $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->putJson('/v1/hcm/promotions/'.$id, [
                'designationTo' => 'Lead Accountant',
            ])->assertOk()
            ->assertJsonPath('success', true);

        // Admin delete
        $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->deleteJson('/v1/hcm/promotions/'.$id)
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_promotion_show_and_per_user_list_self_only(): void
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
            'department' => 'Eng',
            'designationFrom' => 'Staff',
            'designationTo' => 'Senior',
            'promotionDate' => '2026-04-10',
            'notes' => 'ok',
        ];

        $idA = $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->postJson('/v1/hcm/promotions', array_merge(['userId' => $empA->id], $body))
            ->assertStatus(201)
            ->json('data.id');

        $idB = $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->postJson('/v1/hcm/promotions', array_merge(['userId' => $empB->id], $body))
            ->assertStatus(201)
            ->json('data.id');

        $this->withHeaders(['Authorization' => 'Bearer '.$empAToken])
            ->getJson('/v1/hcm/promotions/'.$idA)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->withHeaders(['Authorization' => 'Bearer '.$empAToken])
            ->getJson('/v1/hcm/promotions/'.$idB)
            ->assertStatus(403);

        $this->withHeaders(['Authorization' => 'Bearer '.$empAToken])
            ->getJson('/v1/hcm/promotions/users/'.$empA->id.'/promotions')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->withHeaders(['Authorization' => 'Bearer '.$empAToken])
            ->getJson('/v1/hcm/promotions/users/'.$empB->id.'/promotions')
            ->assertStatus(403);
    }

    public function test_promotion_show_returns_404_when_not_found(): void
    {
        $admin = $this->adminToken();

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->getJson('/v1/hcm/promotions/999999')
            ->assertNotFound();
    }
}

