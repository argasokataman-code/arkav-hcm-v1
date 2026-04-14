<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HcmUserManagementUsersPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_users_page(): void
    {
        $this->get('/users')->assertStatus(404);
    }

    public function test_authenticated_user_can_open_users_page_with_dynamic_controls(): void
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

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->get('/users');

        $response->assertOk();
        $response->assertSee('id="um_users_tbody"', false);
        $response->assertSee('id="um_user_modal"', false);
        $response->assertSee('id="um_role_modal"', false);
        $response->assertSee('build/js/users-management.js', false);
    }
}
