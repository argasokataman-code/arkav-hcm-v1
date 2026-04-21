<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_uses_landing_aligned_auth_shell_and_preserves_auth_form_hooks(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Sign In')
            ->assertSee('id="api-login-form"', false)
            ->assertSee('id="login-email"', false)
            ->assertSee('id="login-password"', false)
            ->assertSee('id="login_mode_regular"', false)
            ->assertSee('id="login_mode_company"', false)
            ->assertSee('Company Code')
            ->assertSee('Daftarkan company di sini')
            ->assertDontSee('Operational readiness');
    }
}