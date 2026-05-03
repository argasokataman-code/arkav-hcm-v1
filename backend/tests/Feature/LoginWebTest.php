<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\PackageAddon;
use App\Models\PurchaseTransaction;
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
            ->assertSee('Selesaikan pembayaran')
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

    public function test_active_subscription_page_shows_paid_addons_dynamically(): void
    {
        $user = User::query()->create([
            'name' => 'Owner Active Addon',
            'email' => 'owner.active.addon@example.com',
            'password' => Hash::make('StrongPass1'),
        ]);

        $company = Company::query()->create([
            'code' => 'active_addon_company',
            'name' => 'Active Addon Company',
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

        $package = Package::query()->create([
            'code' => 'enterprise-addon-view',
            'name' => 'Enterprise',
            'monthly_price' => 1299000,
            'yearly_price' => 12990000,
            'billing_unit' => 'company',
            'status' => 'active',
        ]);

        Subscription::query()->create([
            'company_id' => $company->id,
            'package_uuid' => $package->uuid,
            'plan_code' => $package->code,
            'status' => 'active',
            'starts_at' => now()->startOfDay(),
            'ends_at' => now()->addDays(30),
            'trial_ends_at' => null,
            'auto_renew' => false,
            'billing_cycle' => 'monthly',
            'amount' => 1299000,
        ]);

        $paidAddon = PackageAddon::query()->create([
            'code' => 'shift_scheduler',
            'name' => 'Shift Scheduling',
            'description' => 'Shift scheduler addon',
            'price_per_unit' => 49000,
            'unit_name' => 'tenant / month',
            'status' => 'active',
        ]);

        $unpaidAddon = PackageAddon::query()->create([
            'code' => 'asset_tracker',
            'name' => 'Asset Tracker',
            'description' => 'Asset tracker addon',
            'price_per_unit' => 79000,
            'unit_name' => 'tenant / month',
            'status' => 'active',
        ]);

        PurchaseTransaction::query()->create([
            'transaction_code' => PurchaseTransaction::generateCode(),
            'company_id' => $company->id,
            'package_addon_id' => $paidAddon->id,
            'transaction_type' => 'addon',
            'description' => 'Paid addon checkout',
            'amount' => 49000,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 49000,
            'status' => 'paid',
            'paid_at' => now()->subMinute(),
            'due_date' => now()->addDay(),
        ]);

        PurchaseTransaction::query()->create([
            'transaction_code' => PurchaseTransaction::generateCode(),
            'company_id' => $company->id,
            'package_addon_id' => $unpaidAddon->id,
            'transaction_type' => 'addon',
            'description' => 'Unpaid addon checkout',
            'amount' => 79000,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 79000,
            'status' => 'issued',
            'due_date' => now()->addDay(),
        ]);

        $this->actingAs($user)
            ->withHeader('X-Company-Code', $company->code)
            ->get('/subscription')
            ->assertOk()
            ->assertSee('Add-on Aktif')
            ->assertSee('Shift Scheduling')
            ->assertDontSee('Asset Tracker');
    }

    public function test_authenticated_user_sees_notifications_entry_in_mobile_user_menu(): void
    {
        $user = User::query()->create([
            'name' => 'Owner Mobile Notification',
            'email' => 'owner.mobile.notification@example.com',
            'password' => Hash::make('StrongPass1'),
        ]);

        $company = Company::query()->create([
            'code' => 'mobile_notification_company',
            'name' => 'Mobile Notification Company',
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
            'status' => 'active',
            'starts_at' => now()->startOfDay(),
            'ends_at' => now()->addDays(30),
            'trial_ends_at' => null,
            'auto_renew' => false,
            'billing_cycle' => 'monthly',
            'amount' => 199000,
        ]);

        $this->actingAs($user)
            ->get('/index')
            ->assertOk()
            ->assertSee('href="'.url('notification-settings').'"', false);
    }

    public function test_global_super_admin_is_not_redirected_to_subscription_when_active_company_pending_payment(): void
    {
        $user = User::query()->create([
            'name' => 'Global Super Admin',
            'email' => 'qa.login@example.com',
            'password' => Hash::make('StrongPass1'),
            'is_super_admin' => true,
        ]);

        $company = Company::query()->create([
            'code' => 'pending_for_global_admin',
            'name' => 'Pending For Global Admin',
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
            'billing_cycle' => 'monthly',
            'amount' => 199000,
        ]);

        $this->actingAs($user)
            ->get('/index')
            ->assertOk()
            ->assertDontSee('Akses aplikasi dikunci sampai invoice dibayar.');
    }

    public function test_pending_payment_with_zero_amount_invoice_is_auto_healed_and_not_redirected(): void
    {
        $user = User::query()->create([
            'name' => 'Owner Zero Amount',
            'email' => 'owner.zero.amount@example.com',
            'password' => Hash::make('StrongPass1'),
        ]);

        $company = Company::query()->create([
            'code' => 'zero_amount_company',
            'name' => 'Zero Amount Company',
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

        $subscription = Subscription::query()->create([
            'company_id' => $company->id,
            'package_uuid' => null,
            'plan_code' => 'unlimited',
            'status' => 'pending_payment',
            'starts_at' => now()->startOfDay(),
            'ends_at' => now()->addDays(7),
            'trial_ends_at' => null,
            'auto_renew' => false,
            'billing_cycle' => 'monthly',
            'amount' => 0,
        ]);

        Invoice::query()->create([
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDay()->toDateString(),
            'amount_due' => 0,
            'status' => 'draft',
            'is_paid' => false,
        ]);

        $this->actingAs($user)
            ->get('/index')
            ->assertOk();

        $subscription->refresh();
        $this->assertSame('active', $subscription->status);
    }
}