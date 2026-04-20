<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Package;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaasSubscriptionsAdminOnlyTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_list_or_view_saas_subscriptions(): void
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Trial User',
            'email' => 'trial.user@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $loginResponse = $this->postJson('/v1/identity/auth/login', [
            'email' => 'trial.user@example.com',
            'password' => 'StrongPass1',
        ]);

        $loginResponse->assertOk()->assertCookie((string) config('auth.api_token_cookie.name', 'arcav_access_token'));
        $token = '';
        foreach ($loginResponse->headers->getCookies() as $cookie) {
            if ($cookie->getName() === (string) config('auth.api_token_cookie.name', 'arcav_access_token')) {
                $token = (string) $cookie->getValue();
                break;
            }
        }
        $this->assertNotEmpty($token);
        $cookieHeader = (string) config('auth.api_token_cookie.name', 'arcav_access_token').'='.$token;

        // Resolve default company created by membership migration + ensure membership row exists.
        $company = Company::query()->where('code', 'default_company')->first();
        if (! $company) {
            $company = Company::query()->create([
                'code' => 'default_company',
                'name' => 'Default Company',
                'status' => 'active',
                'owner_user_id' => 1,
                'timezone' => 'Asia/Jakarta',
                'currency' => 'IDR',
                'country_code' => 'ID',
            ]);
        }

        $userId = (int) \App\Models\User::query()->where('email', 'trial.user@example.com')->value('id');
        CompanyUser::query()->updateOrCreate(
            ['company_id' => $company->id, 'user_id' => $userId],
            ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]
        );

        $package = Package::query()->create([
            'code' => 'starter',
            'name' => 'Starter',
            'monthly_price' => 100000,
            'yearly_price' => 1000000,
            'billing_unit' => 'flat',
            'status' => 'active',
        ]);

        $subscription = Subscription::query()->create([
            'company_id' => $company->id,
            'package_uuid' => $package->uuid,
            'plan_code' => $package->code,
            'status' => 'trial',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'trial_ends_at' => now()->addDays(30),
            'auto_renew' => false,
            'billing_cycle' => 'monthly',
            'amount' => $package->monthly_price,
        ]);

        $this->withHeader('Cookie', $cookieHeader)->getJson('/v1/saas/subscriptions')->assertStatus(403);
        $this->withHeader('Cookie', $cookieHeader)->getJson('/v1/saas/subscriptions/'.$subscription->uuid)->assertStatus(403);
    }
}

