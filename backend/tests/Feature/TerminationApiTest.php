<?php

namespace Tests\Feature;

use App\Models\User;
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
                'userId' => $emp->id,
                'department' => 'Finance',
                'terminationType' => 'Layoff',
                'reason' => 'Workforce reduction',
                'noticeDate' => '2026-04-01',
                'terminationDate' => '2026-04-30',
                'notes' => 'OK',
            ])->assertStatus(201);

        $id = $create->json('data.id');
        $this->assertIsInt($id);

        $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->getJson('/v1/hcm/terminations')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->putJson('/v1/hcm/terminations/'.$id, [
                'status' => 'approved',
            ])->assertOk()
            ->assertJsonPath('success', true);

        $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->deleteJson('/v1/hcm/terminations/'.$id)
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
            ->postJson('/v1/hcm/terminations', array_merge(['userId' => $empA->id], $body))
            ->assertStatus(201)
            ->json('data.id');

        $idB = $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->postJson('/v1/hcm/terminations', array_merge(['userId' => $empB->id], $body))
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
}
