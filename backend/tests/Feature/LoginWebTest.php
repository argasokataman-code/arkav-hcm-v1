<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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

    public function test_pending_payment_company_is_redirected_to_subscription_checkout_until_paid(): void
    {
        $user = User::query()->create([
            'name' => 'Owner Pending Payment',
            'email' => 'owner.pending@example.com',
            'password' => Hash::make('StrongPass1'),
        ]);

        $company = Company::query()->create([
            'code' => 'pending_checkout_company',
            'name' => 'Pending Checkout Company',
            'status' => 'active',
            'owner_user_id' => $user->id,
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        Subscription::query()->create([
            'company_id' => $company->id,
            'package_uuid' => null,
            'plan_code' => 'starter',
            'status' => 'pending_payment',
            'starts_at' => now()->startOfDay(),
            'ends_at' => now()->addDays(7),
            'trial_ends_at' => null,
            'auto_renew' => false,
            'billing_cycle' => 'yearly',
            'amount' => 1200000,
        ]);

        $this->actingAs($user)
            ->get('/index')
            ->assertRedirect(url('subscription'));

        $this->actingAs($user)
            ->get('/employees')
            ->assertRedirect(url('subscription'));

        $this->actingAs($user)
            ->get('/users')
            ->assertRedirect(url('subscription'));

        $this->actingAs($user)
            ->get('/subscription')
            ->assertOk()
            ->assertSee('Akses aplikasi dikunci sampai invoice dibayar.')
            ->assertDontSee('MAIN MENU')
            ->assertDontSee('Search in HRMS');
    }

    public function test_trial_company_can_enter_app_and_sees_trial_remaining_badge(): void
    {
        $user = User::query()->create([
            'name' => 'Owner Trial Company',
            'email' => 'owner.trial@example.com',
            'password' => Hash::make('StrongPass1'),
        ]);

        $company = Company::query()->create([
            'code' => 'trial_company_access',
            'name' => 'Trial Company Access',
            'status' => 'active',
            'owner_user_id' => $user->id,
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        Subscription::query()->create([
            'company_id' => $company->id,
            'package_uuid' => null,
            'plan_code' => 'trial',
            'status' => 'trial',
            'starts_at' => now()->startOfDay(),
            'ends_at' => now()->addDays(30),
            'trial_ends_at' => now()->addDays(30),
            'auto_renew' => false,
            'billing_cycle' => 'monthly',
            'amount' => 0,
        ]);

        $this->actingAs($user)
            ->get('/index')
            ->assertOk()
            ->assertSee('Trial')
            ->assertSee('30 hari lagi');
    }
}