<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HcmUserManagementUsersPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_users_page(): void
    {
        $this->get('/users')->assertRedirect(url('lock-screen'));
    }

    public function test_non_admin_is_redirected_from_users_and_roles_permissions_pages(): void
    {
        $password = 'StrongPass1';
        $email = 'users.page.'.time().'@example.com';

        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Users Page Tester',
            'email' => $email,
            'password' => $password,
            'confirmPassword' => $password,
        ])->assertStatus(201);

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => $email,
            'password' => $password,
        ])->assertOk();

        $token = (string) $login->json('data.accessToken');
        $this->assertNotSame('', $token);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->get('/users')
            ->assertRedirect(url('employee-dashboard'));

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->get('/roles-permissions')
            ->assertRedirect(url('employee-dashboard'));
    }
}
