<?php

namespace Tests\Feature;

use App\Mail\RegisterSuccessMailable;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\CompanyUser;
use App\Models\EmployeeProfile;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\PackageFeature;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Tests\TestCase;

#[IgnoreDeprecations]
class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    private function cookieName(): string
    {
        return (string) config('auth.api_token_cookie.name', 'arcav_access_token');
    }

    private function readCookieValueFromLoginResponse(\Illuminate\Testing\TestResponse $response): string
    {
        $setCookies = $response->headers->getCookies();
        foreach ($setCookies as $cookie) {
            if ($cookie->getName() === $this->cookieName()) {
                return (string) $cookie->getValue();
            }
        }

        return '';
    }

    public function test_user_can_register_login_me_and_logout(): void
    {
        Mail::fake();

        $registerResponse = $this->postJson('/v1/identity/auth/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ]);

        $registerResponse
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'john@example.com');

        Mail::assertSent(RegisterSuccessMailable::class, function (RegisterSuccessMailable $mail): bool {
            return (string) $mail->user->email === 'john@example.com';
        });

        $defaultCompany = Company::query()->where('code', 'default_company')->first();
        $this->assertNotNull($defaultCompany);

        $membership = CompanyUser::query()
            ->where('company_id', $defaultCompany->id)
            ->whereHas('user', function ($query): void {
                $query->where('email', 'john@example.com');
            })
            ->first();
        $this->assertNotNull($membership);
        $this->assertSame('member', $membership->role);
        $this->assertSame('active', $membership->status);

        $loginResponse = $this->postJson('/v1/identity/auth/login', [
            'email' => 'john@example.com',
            'password' => 'StrongPass1',
        ]);

        $loginResponse
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['accessToken', 'tokenType', 'expiresIn', 'user'],
            ])
            ->assertCookie($this->cookieName());

        $token = $this->readCookieValueFromLoginResponse($loginResponse);
        $this->assertNotEmpty($token, 'Cookie token was not set.');

        $cookieHeader = $this->cookieName().'='.$token;

        $meResponse = $this->withHeader('Cookie', $cookieHeader)
            ->getJson('/v1/identity/auth/me');

        $meResponse
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email', 'john@example.com');

        $logoutResponse = $this->withHeader('Cookie', $cookieHeader)
            ->postJson('/v1/identity/auth/logout');

        $logoutResponse
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertCookieExpired($this->cookieName());

        $this->getJson('/v1/identity/auth/me')->assertStatus(401);
    }

    public function test_login_returns_validation_error_for_bad_payload(): void
    {
        $response = $this->postJson('/v1/identity/auth/login', [
            'email' => 'invalid-email',
            'password' => 'short',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_trial_owner_is_treated_as_tenant_admin_on_me(): void
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Trial Owner',
            'email' => 'trial.owner@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $user = User::query()->where('email', 'trial.owner@example.com')->first();
        $this->assertNotNull($user);

        $defaultCompany = Company::query()->where('code', 'default_company')->first();
        $this->assertNotNull($defaultCompany);

        $membership = CompanyUser::query()
            ->where('company_id', $defaultCompany->id)
            ->where('user_id', $user->id)
            ->first();
        $this->assertNotNull($membership);

        $membership->update(['role' => 'owner']);

        $loginResponse = $this->postJson('/v1/identity/auth/login', [
            'email' => 'trial.owner@example.com',
            'password' => 'StrongPass1',
            'companyCode' => $defaultCompany->code,
        ])->assertOk()->assertCookie($this->cookieName());

        $token = $this->readCookieValueFromLoginResponse($loginResponse);
        $this->assertNotEmpty($token);

        $meResponse = $this->withHeader('Cookie', $this->cookieName().'='.$token)
            ->getJson('/v1/identity/auth/me');

        $meResponse
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email', 'trial.owner@example.com')
            ->assertJsonPath('data.activeCompany.code', 'default_company')
            ->assertJsonPath('data.activeCompany.role', 'owner')
            ->assertJsonPath('data.currentUserRole', 'owner')
            ->assertJsonPath('data.hcmAdmin', true);
    }

    public function test_company_owner_me_returns_subscription_summary_without_employee_role_snapshot(): void
    {
        $package = Package::query()->create([
            'code' => 'professional',
            'name' => 'Professional',
            'monthly_price' => 250000,
            'yearly_price' => 2400000,
            'billing_unit' => 'flat',
            'status' => 'active',
        ]);

        $user = User::query()->create([
            'name' => 'Owner Snapshot',
            'email' => 'owner.snapshot@example.com',
            'password' => Hash::make('StrongPass1'),
        ]);

        $company = Company::query()->create([
            'code' => 'owner_snapshot_co',
            'name' => 'Owner Snapshot Co',
            'legal_name' => 'Owner Snapshot Co LLC',
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
            'invited_by_user_id' => null,
        ]);

        CompanySetting::query()->create([
            'company_id' => $company->id,
            'key' => 'owner_phone',
            'value' => '081234567890',
            'type' => 'string',
        ]);

        PackageFeature::query()->create([
            'package_uuid' => $package->uuid,
            'feature_code' => 'max_employees',
            'feature_name' => 'Maximum Employees',
            'limit' => 25,
        ]);

        $employeeUser = User::query()->create([
            'name' => 'Company Employee',
            'email' => 'company.employee@example.com',
            'password' => Hash::make('StrongPass1'),
        ]);

        EmployeeProfile::query()->create([
            'company_id' => $company->id,
            'user_id' => $employeeUser->id,
            'employment_status' => 'active',
            'designation' => 'Staff',
            'team' => 'Operations',
        ]);

        $subscription = Subscription::query()->create([
            'company_id' => $company->id,
            'package_uuid' => $package->uuid,
            'plan_code' => 'professional',
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'trial_ends_at' => null,
            'auto_renew' => false,
            'billing_cycle' => 'yearly',
            'amount' => 2400000,
        ]);

        $invoice = Invoice::query()->create([
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'amount_due' => 2400000,
            'status' => 'draft',
            'is_paid' => false,
            'notes' => 'Professional plan invoice',
        ]);

        $loginResponse = $this->postJson('/v1/identity/auth/login', [
            'email' => 'owner.snapshot@example.com',
            'password' => 'StrongPass1',
            'companyCode' => 'owner_snapshot_co',
        ])->assertOk();

        $token = $this->readCookieValueFromLoginResponse($loginResponse);
        $cookieHeader = $this->cookieName().'='.$token;

        $this->withHeader('Cookie', $cookieHeader)
            ->getJson('/v1/identity/auth/me')
            ->assertOk()
            ->assertJsonPath('data.currentUserRole', 'owner')
            ->assertJsonPath('data.roles.0', 'owner')
            ->assertJsonPath('data.profile.source', 'company_owner_profile')
            ->assertJsonPath('data.profile.phone', '081234567890')
            ->assertJsonPath('data.subscription.packageCode', 'professional')
            ->assertJsonPath('data.subscription.packageName', 'Professional')
            ->assertJsonPath('data.subscription.billingCycle', 'yearly')
            ->assertJsonPath('data.subscription.nextPayment.invoiceId', $invoice->id)
            ->assertJsonPath('data.subscription.nextPayment.invoiceNumber', $invoice->invoice_number)
            ->assertJsonPath('data.subscription.nextPayment.amount', 2400000)
            ->assertJsonPath('data.subscription.employeeSlots.limit', 25)
            ->assertJsonPath('data.subscription.employeeSlots.used', 1)
            ->assertJsonPath('data.subscription.employeeSlots.remaining', 24)
            ->assertJsonPath('data.subscription.employeeSlots.isConfigured', true)
            ->assertJsonPath('data.subscription.employeeSlots.isOverLimit', false);
    }

    public function test_company_owner_me_uses_nearest_unpaid_invoice_for_next_payment_summary(): void
    {
        $package = Package::query()->create([
            'code' => 'enterprise',
            'name' => 'Enterprise',
            'monthly_price' => 1299000,
            'yearly_price' => 12990000,
            'billing_unit' => 'flat',
            'status' => 'active',
        ]);

        $user = User::query()->create([
            'name' => 'Owner Next Payment',
            'email' => 'owner.next-payment@example.com',
            'password' => Hash::make('StrongPass1'),
        ]);

        $company = Company::query()->create([
            'code' => 'owner_next_payment_co',
            'name' => 'Owner Next Payment Co',
            'legal_name' => 'Owner Next Payment Co LLC',
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
            'invited_by_user_id' => null,
        ]);

        $subscription = Subscription::query()->create([
            'company_id' => $company->id,
            'package_uuid' => $package->uuid,
            'plan_code' => 'enterprise',
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'trial_ends_at' => null,
            'auto_renew' => false,
            'billing_cycle' => 'monthly',
            'amount' => 1299000,
        ]);

        $laterInvoice = Invoice::query()->create([
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(20)->toDateString(),
            'amount_due' => 1999000,
            'status' => 'draft',
            'is_paid' => false,
            'notes' => 'Later unpaid invoice',
        ]);

        $nearestInvoice = Invoice::query()->create([
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(3)->toDateString(),
            'amount_due' => 1299000,
            'status' => 'draft',
            'is_paid' => false,
            'notes' => 'Nearest unpaid invoice',
        ]);

        $loginResponse = $this->postJson('/v1/identity/auth/login', [
            'email' => 'owner.next-payment@example.com',
            'password' => 'StrongPass1',
            'companyCode' => 'owner_next_payment_co',
        ])->assertOk();

        $token = $this->readCookieValueFromLoginResponse($loginResponse);
        $cookieHeader = $this->cookieName().'='.$token;

        $response = $this->withHeader('Cookie', $cookieHeader)
            ->getJson('/v1/identity/auth/me')
            ->assertOk()
            ->assertJsonPath('data.subscription.nextPayment.invoiceId', $nearestInvoice->id)
            ->assertJsonPath('data.subscription.nextPayment.invoiceNumber', $nearestInvoice->invoice_number)
            ->assertJsonPath('data.subscription.nextPayment.amount', 1299000);

        $this->assertNotSame(
            $laterInvoice->id,
            (int) data_get($response->json(), 'data.subscription.nextPayment.invoiceId')
        );
    }

    public function test_remember_me_login_has_longer_expiry_than_regular_login(): void
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $regular = $this->postJson('/v1/identity/auth/login', [
            'email' => 'john@example.com',
            'password' => 'StrongPass1',
            'rememberMe' => false,
        ])->assertStatus(200);

        $remember = $this->postJson('/v1/identity/auth/login', [
            'email' => 'john@example.com',
            'password' => 'StrongPass1',
            'rememberMe' => true,
        ])->assertStatus(200);

        $this->assertGreaterThan(
            (int) $regular->json('data.expiresIn'),
            (int) $remember->json('data.expiresIn')
        );
    }

    public function test_login_is_throttled_after_multiple_failed_attempts(): void
    {
        RateLimiter::clear('auth:login:john@example.com|127.0.0.1');

        $this->postJson('/v1/identity/auth/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/v1/identity/auth/login', [
                'email' => 'john@example.com',
                'password' => 'WrongPass1',
            ])->assertStatus(401);
        }

        $this->postJson('/v1/identity/auth/login', [
            'email' => 'john@example.com',
            'password' => 'WrongPass1',
        ])->assertStatus(429)
            ->assertJsonPath('error.code', 'AUTH_TOO_MANY_ATTEMPTS');
    }

    public function test_me_returns_active_company_context(): void
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Tenant User',
            'email' => 'tenant.user@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $loginResponse = $this->postJson('/v1/identity/auth/login', [
            'email' => 'tenant.user@example.com',
            'password' => 'StrongPass1',
        ])->assertOk();

        $token = $this->readCookieValueFromLoginResponse($loginResponse);
        $cookieHeader = $this->cookieName().'='.$token;

        $this->withHeader('Cookie', $cookieHeader)
            ->getJson('/v1/identity/auth/me')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email', 'tenant.user@example.com')
            ->assertJsonPath('data.activeCompany.code', 'default_company')
            ->assertJsonPath('data.activeCompany.role', 'member');
    }

    public function test_user_can_update_profile_via_identity_endpoint(): void
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Profile User',
            'email' => 'profile.user@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $loginResponse = $this->postJson('/v1/identity/auth/login', [
            'email' => 'profile.user@example.com',
            'password' => 'StrongPass1',
        ])->assertOk();

        $token = $this->readCookieValueFromLoginResponse($loginResponse);
        $cookieHeader = $this->cookieName().'='.$token;

        $this->withHeader('Cookie', $cookieHeader)
            ->putJson('/v1/identity/auth/profile', [
                'name' => 'Profile Updated',
                'email' => 'profile.updated@example.com',
                'phone' => '08123456789',
                'address' => 'Jl. Merdeka 1',
                'addressDetail' => 'Jakarta',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Profile Updated')
            ->assertJsonPath('data.email', 'profile.updated@example.com')
            ->assertJsonPath('data.profile.phone', '08123456789')
            ->assertJsonPath('data.profile.address', 'Jl. Merdeka 1')
            ->assertJsonPath('data.profile.addressDetail', 'Jakarta');

        $user = User::query()->where('email', 'profile.updated@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('Profile Updated', $user->name);

        $profile = EmployeeProfile::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($profile);
        $this->assertSame('08123456789', $profile->phone);
        $this->assertSame('Jl. Merdeka 1', $profile->address);
        $this->assertSame('Jakarta', $profile->address_detail);
    }

    public function test_profile_update_rejects_invalid_phone_and_address_detail_format(): void
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Regex Guard User',
            'email' => 'regex.guard.user@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $loginResponse = $this->postJson('/v1/identity/auth/login', [
            'email' => 'regex.guard.user@example.com',
            'password' => 'StrongPass1',
        ])->assertOk();

        $token = $this->readCookieValueFromLoginResponse($loginResponse);
        $cookieHeader = $this->cookieName().'='.$token;

        $this->withHeader('Cookie', $cookieHeader)
            ->putJson('/v1/identity/auth/profile', [
                'name' => 'Regex Guard User',
                'email' => 'regex.guard.user@example.com',
                'phone' => 'ABC@@@123',
                'addressDetail' => 'Jakarta#1',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonFragment(['field' => 'phone'])
            ->assertJsonFragment(['field' => 'addressDetail']);
    }

    public function test_profile_update_rejects_phone_with_too_many_digits(): void
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Phone Length Guard',
            'email' => 'phone.length.guard@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $loginResponse = $this->postJson('/v1/identity/auth/login', [
            'email' => 'phone.length.guard@example.com',
            'password' => 'StrongPass1',
        ])->assertOk();

        $token = $this->readCookieValueFromLoginResponse($loginResponse);
        $cookieHeader = $this->cookieName().'='.$token;

        $this->withHeader('Cookie', $cookieHeader)
            ->putJson('/v1/identity/auth/profile', [
                'name' => 'Phone Length Guard',
                'email' => 'phone.length.guard@example.com',
                'phone' => '15827358176253817562',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonFragment(['field' => 'phone']);
    }

    public function test_owner_profile_update_uses_company_settings_without_creating_employee_profile(): void
    {
        $user = User::query()->create([
            'name' => 'Owner Profile',
            'email' => 'owner.profile@example.com',
            'password' => Hash::make('StrongPass1'),
        ]);

        $company = Company::query()->create([
            'code' => 'owner_profile_company',
            'name' => 'Owner Profile Company',
            'legal_name' => 'Owner Profile Company LLC',
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
            'invited_by_user_id' => null,
        ]);

        $loginResponse = $this->postJson('/v1/identity/auth/login', [
            'email' => 'owner.profile@example.com',
            'password' => 'StrongPass1',
            'companyCode' => 'owner_profile_company',
        ])->assertOk();

        $token = $this->readCookieValueFromLoginResponse($loginResponse);
        $cookieHeader = $this->cookieName().'='.$token;

        $this->withHeader('Cookie', $cookieHeader)
            ->putJson('/v1/identity/auth/profile', [
                'name' => 'Owner Profile Updated',
                'email' => 'owner.profile.updated@example.com',
                'phone' => '081111111111',
                'address' => 'Jl. Owner 1',
                'addressDetail' => 'Bandung',
                'companyName' => 'Owner Profile Company Updated',
                'companyLegalName' => 'Owner Profile Company Holdings LLC',
                'companyAddress' => 'Jl. Billing 77',
                'companyCity' => 'Jakarta',
                'companyState' => 'DKI Jakarta',
                'companyCountry' => 'Indonesia',
                'companyPostalCode' => '10270',
                'companyNpwp' => '12.345.678.9-012.345',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Owner Profile Updated')
            ->assertJsonPath('data.currentUserRole', 'owner')
            ->assertJsonPath('data.profile.source', 'company_owner_profile')
            ->assertJsonPath('data.profile.phone', '081111111111')
            ->assertJsonPath('data.profile.address', 'Jl. Owner 1')
            ->assertJsonPath('data.profile.addressDetail', 'Bandung')
            ->assertJsonPath('data.companyProfile.name', 'Owner Profile Company Updated')
            ->assertJsonPath('data.companyProfile.legalName', 'Owner Profile Company Holdings LLC')
            ->assertJsonPath('data.companyProfile.address', 'Jl. Billing 77')
            ->assertJsonPath('data.companyProfile.city', 'Jakarta')
            ->assertJsonPath('data.companyProfile.state', 'DKI Jakarta')
            ->assertJsonPath('data.companyProfile.country', 'Indonesia')
            ->assertJsonPath('data.companyProfile.postalCode', '10270')
            ->assertJsonPath('data.companyProfile.npwp', '123456789012345');

        $updatedUser = User::query()->where('email', 'owner.profile.updated@example.com')->first();
        $this->assertNotNull($updatedUser);
        $this->assertFalse(EmployeeProfile::query()->where('user_id', $updatedUser->id)->exists());
        $this->assertDatabaseHas('company_settings', [
            'company_id' => $company->id,
            'key' => 'owner_phone',
            'value' => '081111111111',
        ]);
        $this->assertDatabaseHas('company_settings', [
            'company_id' => $company->id,
            'key' => 'owner_address',
            'value' => 'Jl. Owner 1',
        ]);
        $this->assertDatabaseHas('company_settings', [
            'company_id' => $company->id,
            'key' => 'owner_address_detail',
            'value' => 'Bandung',
        ]);
        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'name' => 'Owner Profile Company Updated',
            'legal_name' => 'Owner Profile Company Holdings LLC',
        ]);
        $this->assertDatabaseHas('company_settings', [
            'company_id' => $company->id,
            'key' => 'company_profile_address',
            'value' => 'Jl. Billing 77',
        ]);
        $this->assertDatabaseHas('company_settings', [
            'company_id' => $company->id,
            'key' => 'company_profile_city',
            'value' => 'Jakarta',
        ]);
        $this->assertDatabaseHas('company_settings', [
            'company_id' => $company->id,
            'key' => 'company_profile_state',
            'value' => 'DKI Jakarta',
        ]);
        $this->assertDatabaseHas('company_settings', [
            'company_id' => $company->id,
            'key' => 'company_profile_country',
            'value' => 'Indonesia',
        ]);
        $this->assertDatabaseHas('company_settings', [
            'company_id' => $company->id,
            'key' => 'company_profile_postal_code',
            'value' => '10270',
        ]);
        $this->assertDatabaseHas('company_settings', [
            'company_id' => $company->id,
            'key' => 'company_profile_npwp',
            'value' => '123456789012345',
        ]);

        $this->withHeader('Cookie', $cookieHeader)
            ->getJson('/v1/identity/auth/me')
            ->assertOk()
            ->assertJsonPath('data.companyProfile.name', 'Owner Profile Company Updated')
            ->assertJsonPath('data.companyProfile.legalName', 'Owner Profile Company Holdings LLC')
            ->assertJsonPath('data.companyProfile.address', 'Jl. Billing 77')
            ->assertJsonPath('data.companyProfile.city', 'Jakarta')
            ->assertJsonPath('data.companyProfile.state', 'DKI Jakarta')
            ->assertJsonPath('data.companyProfile.country', 'Indonesia')
            ->assertJsonPath('data.companyProfile.postalCode', '10270')
            ->assertJsonPath('data.companyProfile.npwp', '123456789012345')
            ->assertJsonPath('data.activeCompany.legalName', 'Owner Profile Company Holdings LLC');
    }

    public function test_owner_profile_update_with_existing_employee_profile_still_updates_company_profile(): void
    {
        $user = User::query()->create([
            'name' => 'Owner Employee Profile',
            'email' => 'owner.employee.profile@example.com',
            'password' => Hash::make('StrongPass1'),
        ]);

        $company = Company::query()->create([
            'code' => 'owner_employee_profile_company',
            'name' => 'Owner Employee Profile Company',
            'legal_name' => 'Owner Employee Profile Company LLC',
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
            'invited_by_user_id' => null,
        ]);

        EmployeeProfile::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'phone' => '080000000000',
            'address' => 'Jl. Existing Employee Profile',
            'address_detail' => 'Bandung',
            'employment_status' => 'active',
            'designation' => 'Owner',
            'team' => 'Management',
        ]);

        $loginResponse = $this->postJson('/v1/identity/auth/login', [
            'email' => 'owner.employee.profile@example.com',
            'password' => 'StrongPass1',
            'companyCode' => 'owner_employee_profile_company',
        ])->assertOk();

        $token = $this->readCookieValueFromLoginResponse($loginResponse);
        $cookieHeader = $this->cookieName().'='.$token;

        $this->withHeader('Cookie', $cookieHeader)
            ->putJson('/v1/identity/auth/profile', [
                'name' => 'Owner Employee Profile Updated',
                'email' => 'owner.employee.profile.updated@example.com',
                'phone' => '081333333333',
                'address' => 'Jl. Existing Employee Profile Updated',
                'addressDetail' => 'Jakarta',
                'companyName' => 'Owner Employee Profile Company Updated',
                'companyLegalName' => 'Owner Employee Profile Company Holdings LLC',
                'companyAddress' => 'Jl. Billing Existing 88',
                'companyCity' => 'Jakarta',
                'companyState' => 'DKI Jakarta',
                'companyCountry' => 'Indonesia',
                'companyPostalCode' => '12950',
                'companyNpwp' => '123456789012345',
            ])
            ->assertOk()
            ->assertJsonPath('data.currentUserRole', 'owner')
            ->assertJsonPath('data.profile.source', 'employee_profile')
            ->assertJsonPath('data.profile.phone', '081333333333')
            ->assertJsonPath('data.companyProfile.name', 'Owner Employee Profile Company Updated')
            ->assertJsonPath('data.companyProfile.legalName', 'Owner Employee Profile Company Holdings LLC')
            ->assertJsonPath('data.companyProfile.address', 'Jl. Billing Existing 88')
            ->assertJsonPath('data.companyProfile.postalCode', '12950')
            ->assertJsonPath('data.companyProfile.npwp', '123456789012345');

        $updatedUser = User::query()->where('email', 'owner.employee.profile.updated@example.com')->first();
        $this->assertNotNull($updatedUser);
        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'name' => 'Owner Employee Profile Company Updated',
            'legal_name' => 'Owner Employee Profile Company Holdings LLC',
        ]);
        $this->assertDatabaseHas('company_settings', [
            'company_id' => $company->id,
            'key' => 'company_profile_address',
            'value' => 'Jl. Billing Existing 88',
        ]);
        $this->assertDatabaseHas('company_settings', [
            'company_id' => $company->id,
            'key' => 'company_profile_postal_code',
            'value' => '12950',
        ]);
        $this->assertDatabaseHas('company_settings', [
            'company_id' => $company->id,
            'key' => 'company_profile_npwp',
            'value' => '123456789012345',
        ]);

        $this->withHeader('Cookie', $cookieHeader)
            ->getJson('/v1/identity/auth/me')
            ->assertOk()
            ->assertJsonPath('data.profile.source', 'employee_profile')
            ->assertJsonPath('data.companyProfile.name', 'Owner Employee Profile Company Updated')
            ->assertJsonPath('data.companyProfile.legalName', 'Owner Employee Profile Company Holdings LLC')
            ->assertJsonPath('data.companyProfile.address', 'Jl. Billing Existing 88')
            ->assertJsonPath('data.companyProfile.postalCode', '12950')
            ->assertJsonPath('data.companyProfile.npwp', '123456789012345')
            ->assertJsonPath('data.activeCompany.legalName', 'Owner Employee Profile Company Holdings LLC');
    }

    public function test_owner_profile_update_rejects_invalid_company_npwp(): void
    {
        $user = User::query()->create([
            'name' => 'Owner Invalid NPWP',
            'email' => 'owner.invalid.npwp@example.com',
            'password' => Hash::make('StrongPass1'),
        ]);

        $company = Company::query()->create([
            'code' => 'owner_invalid_npwp_company',
            'name' => 'Owner Invalid NPWP Company',
            'legal_name' => 'Owner Invalid NPWP Company LLC',
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
            'invited_by_user_id' => null,
        ]);

        $loginResponse = $this->postJson('/v1/identity/auth/login', [
            'email' => 'owner.invalid.npwp@example.com',
            'password' => 'StrongPass1',
            'companyCode' => 'owner_invalid_npwp_company',
        ])->assertOk();

        $token = $this->readCookieValueFromLoginResponse($loginResponse);
        $cookieHeader = $this->cookieName().'='.$token;

        $this->withHeader('Cookie', $cookieHeader)
            ->putJson('/v1/identity/auth/profile', [
                'name' => 'Owner Invalid NPWP Updated',
                'email' => 'owner.invalid.npwp.updated@example.com',
                'companyName' => 'Owner Invalid NPWP Company Updated',
                'companyNpwp' => '12.34',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_profile_password_update_requires_valid_current_password(): void
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Password User',
            'email' => 'password.user@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $loginResponse = $this->postJson('/v1/identity/auth/login', [
            'email' => 'password.user@example.com',
            'password' => 'StrongPass1',
        ])->assertOk();

        $token = $this->readCookieValueFromLoginResponse($loginResponse);
        $cookieHeader = $this->cookieName().'='.$token;

        $this->withHeader('Cookie', $cookieHeader)
            ->putJson('/v1/identity/auth/profile', [
                'name' => 'Password User',
                'email' => 'password.user@example.com',
                'currentPassword' => 'WrongPass1',
                'newPassword' => 'NewStrongPass1',
                'confirmPassword' => 'NewStrongPass1',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'AUTH_INVALID_CREDENTIALS');

        $this->withHeader('Cookie', $cookieHeader)
            ->putJson('/v1/identity/auth/profile', [
                'name' => 'Password User',
                'email' => 'password.user@example.com',
                'currentPassword' => 'StrongPass1',
                'newPassword' => 'NewStrongPass1',
                'confirmPassword' => 'NewStrongPass1',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->postJson('/v1/identity/auth/login', [
            'email' => 'password.user@example.com',
            'password' => 'StrongPass1',
        ])->assertStatus(401)
            ->assertJsonPath('error.code', 'AUTH_INVALID_CREDENTIALS');

        $this->postJson('/v1/identity/auth/login', [
            'email' => 'password.user@example.com',
            'password' => 'NewStrongPass1',
        ])->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_hcm_route_forbidden_when_requesting_unowned_company(): void
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Tenant User Two',
            'email' => 'tenant.user2@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $loginResponse = $this->postJson('/v1/identity/auth/login', [
            'email' => 'tenant.user2@example.com',
            'password' => 'StrongPass1',
        ])->assertOk();

        $token = $this->readCookieValueFromLoginResponse($loginResponse);
        $cookieHeader = $this->cookieName().'='.$token;

        $otherCompany = Company::query()->create([
            'code' => 'other_company',
            'name' => 'Other Company',
            'legal_name' => 'Other Company LLC',
            'status' => 'active',
            'owner_user_id' => null,
            'timezone' => 'UTC',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $this->withHeaders([
            'Cookie' => $cookieHeader,
            'X-Company-Id' => (string) $otherCompany->id,
        ])->getJson('/v1/hcm/attendance/me/today')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'TENANT_FORBIDDEN');
    }

    public function test_login_backfills_default_company_membership_for_legacy_user(): void
    {
        $user = User::create([
            'name' => 'Legacy User',
            'email' => 'legacy.user@example.com',
            'password' => Hash::make('StrongPass1'),
        ]);

        $this->assertFalse(
            CompanyUser::query()->where('user_id', $user->id)->exists(),
            'Precondition failed: legacy user should start without company membership.'
        );

        $loginResponse = $this->postJson('/v1/identity/auth/login', [
            'email' => 'legacy.user@example.com',
            'password' => 'StrongPass1',
        ]);

        $loginResponse
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertCookie($this->cookieName());

        $token = $this->readCookieValueFromLoginResponse($loginResponse);
        $cookieHeader = $this->cookieName().'='.$token;

        $this->withHeader('Cookie', $cookieHeader)
            ->getJson('/v1/identity/auth/me')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.activeCompany.code', 'default_company');

        $membership = CompanyUser::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        $this->assertNotNull($membership);
        $this->assertSame('member', $membership->role);
    }

    public function test_login_as_company_succeeds_for_member_company_code(): void
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Company Member',
            'email' => 'company.member@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $user = User::query()->where('email', 'company.member@example.com')->firstOrFail();

        $company = Company::query()->create([
            'code' => 'acme_company',
            'name' => 'Acme Company',
            'legal_name' => 'Acme Company Ltd',
            'status' => 'active',
            'owner_user_id' => null,
            'timezone' => 'UTC',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => 'admin',
            'status' => 'active',
            'joined_at' => now(),
            'invited_by_user_id' => null,
        ]);

        $this->postJson('/v1/identity/auth/login', [
            'email' => 'company.member@example.com',
            'password' => 'StrongPass1',
            'companyCode' => 'acme_company',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.activeCompany.code', 'acme_company')
            ->assertJsonPath('data.activeCompany.role', 'admin');
    }

    public function test_login_as_company_fails_for_unowned_company_code(): void
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Company Forbidden',
            'email' => 'company.forbidden@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        Company::query()->create([
            'code' => 'forbidden_company',
            'name' => 'Forbidden Company',
            'legal_name' => 'Forbidden Company LLC',
            'status' => 'active',
            'owner_user_id' => null,
            'timezone' => 'UTC',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $this->postJson('/v1/identity/auth/login', [
            'email' => 'company.forbidden@example.com',
            'password' => 'StrongPass1',
            'companyCode' => 'forbidden_company',
        ])->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'TENANT_FORBIDDEN');
    }

    public function test_company_owner_cannot_login_without_company_code(): void
    {
        $user = User::query()->create([
            'name' => 'Owner Must Use Company Mode',
            'email' => 'owner.companymode@example.com',
            'password' => Hash::make('StrongPass1'),
        ]);

        $company = Company::query()->create([
            'code' => 'owner_mode_only',
            'name' => 'Owner Mode Only',
            'legal_name' => 'Owner Mode Only LLC',
            'status' => 'active',
            'owner_user_id' => $user->id,
            'timezone' => 'UTC',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
            'invited_by_user_id' => null,
        ]);

        $this->postJson('/v1/identity/auth/login', [
            'email' => 'owner.companymode@example.com',
            'password' => 'StrongPass1',
        ])->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'AUTH_COMPANY_MODE_REQUIRED');
    }

    public function test_company_active_endpoint_returns_details(): void
    {
        $user = User::query()->create([
            'name' => 'Company Detail User',
            'email' => 'company.detail@example.com',
            'password' => Hash::make('StrongPass1'),
        ]);

        $company = Company::query()->create([
            'code' => 'test_company_detail',
            'name' => 'Test Company Detail',
            'legal_name' => 'Test Company Detail Inc',
            'status' => 'active',
            'owner_user_id' => $user->id,
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $companyUser = CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now()->subDay(),
            'invited_by_user_id' => null,
        ]);

        $loginResponse = $this->postJson('/v1/identity/auth/login', [
            'email' => 'company.detail@example.com',
            'password' => 'StrongPass1',
            'companyCode' => 'test_company_detail',
        ])->assertStatus(200);

        $response = $this->getJson('/v1/hcm/company/active', [
            'Authorization' => 'Bearer '.$loginResponse->json('data.accessToken'),
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.code', 'test_company_detail')
            ->assertJsonPath('data.name', 'Test Company Detail')
            ->assertJsonPath('data.legalName', 'Test Company Detail Inc')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.timezone', 'Asia/Jakarta')
            ->assertJsonPath('data.currency', 'IDR')
            ->assertJsonPath('data.countryCode', 'ID')
            ->assertJsonPath('data.currentUserRole', 'owner')
            ->assertJsonPath('data.memberCount', 1)
            ->assertJsonPath('data.owner.name', 'Company Detail User')
            ->assertJsonPath('data.createdAt', $company->created_at->toIso8601String())
            ->assertJsonPath('data.updatedAt', $company->updated_at->toIso8601String());
    }

    public function test_company_list_endpoint_returns_paginated_companies(): void
    {
        $admin = User::create([
            'name' => 'Admin One',
            'email' => 'qa.login@example.com',
            'password' => Hash::make('StrongPass1'),
        ]);

        $company1 = Company::create([
            'code' => 'company_1',
            'name' => 'Company One',
            'legal_name' => 'Company One Inc',
            'status' => 'active',
            'owner_user_id' => $admin->id,
            'timezone' => 'UTC',
            'currency' => 'USD',
            'country_code' => 'US',
        ]);

        $company2 = Company::create([
            'code' => 'company_2',
            'name' => 'Company Two',
            'legal_name' => 'Company Two Inc',
            'status' => 'inactive',
            'owner_user_id' => $admin->id,
            'timezone' => 'UTC',
            'currency' => 'USD',
            'country_code' => 'US',
        ]);

        $loginResponse = $this->postJson('/v1/identity/auth/login', [
            'email' => 'qa.login@example.com',
            'password' => 'StrongPass1',
        ])->assertStatus(200);

        $response = $this->getJson('/v1/company?page=1&per_page=10', [
            'Authorization' => 'Bearer '.$loginResponse->json('data.accessToken'),
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pagination.total', 3)
            ->assertJsonPath('data.pagination.page', 1)
            ->assertJsonPath('data.pagination.per_page', 10);
    }

    public function test_company_list_endpoint_filters_by_status(): void
    {
        $admin = User::create([
            'name' => 'Admin Two',
            'email' => 'qa.login2@example.com',
            'password' => Hash::make('StrongPass1'),
        ]);

        Company::create([
            'code' => 'active_company',
            'name' => 'Active Company',
            'legal_name' => 'Active Company Inc',
            'status' => 'active',
            'owner_user_id' => $admin->id,
            'timezone' => 'UTC',
            'currency' => 'USD',
            'country_code' => 'US',
        ]);

        Company::create([
            'code' => 'inactive_company',
            'name' => 'Inactive Company',
            'legal_name' => 'Inactive Company Inc',
            'status' => 'inactive',
            'owner_user_id' => $admin->id,
            'timezone' => 'UTC',
            'currency' => 'USD',
            'country_code' => 'US',
        ]);

        $loginResponse = $this->postJson('/v1/identity/auth/login', [
            'email' => 'qa.login2@example.com',
            'password' => 'StrongPass1',
        ])->assertStatus(200);

        $response = $this->getJson('/v1/company?status=active', [
            'Authorization' => 'Bearer '.$loginResponse->json('data.accessToken'),
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.companies.0.status', 'active');
    }

    public function test_company_create_endpoint_requires_admin(): void
    {
        $user = User::create([
            'name' => 'Regular User',
            'email' => 'user@example.com',
            'password' => Hash::make('StrongPass1'),
        ]);

        $loginResponse = $this->postJson('/v1/identity/auth/login', [
            'email' => 'user@example.com',
            'password' => 'StrongPass1',
        ])->assertStatus(200);

        $response = $this->postJson('/v1/company', [
            'code' => 'new_company',
            'name' => 'New Company',
            'legal_name' => 'New Company Inc',
            'status' => 'active',
            'timezone' => 'UTC',
            'currency' => 'USD',
            'country_code' => 'US',
        ], [
            'Authorization' => 'Bearer '.$loginResponse->json('data.accessToken'),
        ]);

        $response->assertStatus(403)->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_company_create_endpoint_creates_company_for_admin(): void
    {
        $admin = User::create([
            'name' => 'QA Admin',
            'email' => 'qa.login@example.com',
            'password' => Hash::make('StrongPass1'),
        ]);

        $loginResponse = $this->postJson('/v1/identity/auth/login', [
            'email' => 'qa.login@example.com',
            'password' => 'StrongPass1',
        ])->assertStatus(200);

        $response = $this->postJson('/v1/company', [
            'code' => 'new_company_test',
            'name' => 'New Company Test',
            'legal_name' => 'New Company Test Inc',
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ], [
            'Authorization' => 'Bearer '.$loginResponse->json('data.accessToken'),
        ]);

        $response
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.code', 'new_company_test')
            ->assertJsonPath('data.name', 'New Company Test')
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('companies', [
            'code' => 'new_company_test',
            'name' => 'New Company Test',
        ]);
    }

    public function test_company_update_endpoint_updates_company(): void
    {
        $admin = User::create([
            'name' => 'Admin Four',
            'email' => 'qa.login4@example.com',
            'password' => Hash::make('StrongPass1'),
        ]);

        $company = Company::create([
            'code' => 'update_test',
            'name' => 'Update Test Company',
            'legal_name' => 'Update Test Inc',
            'status' => 'active',
            'owner_user_id' => $admin->id,
            'timezone' => 'UTC',
            'currency' => 'USD',
            'country_code' => 'US',
        ]);

        $loginResponse = $this->postJson('/v1/identity/auth/login', [
            'email' => 'qa.login4@example.com',
            'password' => 'StrongPass1',
        ])->assertStatus(200);

        $response = $this->putJson('/v1/company/'.$company->id, [
            'name' => 'Updated Company Name',
            'status' => 'inactive',
        ], [
            'Authorization' => 'Bearer '.$loginResponse->json('data.accessToken'),
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Updated Company Name')
            ->assertJsonPath('data.status', 'inactive');

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'name' => 'Updated Company Name',
            'status' => 'inactive',
        ]);
    }

    public function test_company_delete_endpoint_requires_admin(): void
    {
        $user = User::create([
            'name' => 'Delete User',
            'email' => 'user_delete@example.com',
            'password' => Hash::make('StrongPass1'),
        ]);
        $admin = User::create([
            'name' => 'Admin Five',
            'email' => 'qa.login5@example.com',
            'password' => Hash::make('StrongPass1'),
        ]);

        $company = Company::create([
            'code' => 'delete_test',
            'name' => 'Delete Test Company',
            'legal_name' => 'Delete Test Inc',
            'status' => 'active',
            'owner_user_id' => $admin->id,
            'timezone' => 'UTC',
            'currency' => 'USD',
            'country_code' => 'US',
        ]);

        $loginResponse = $this->postJson('/v1/identity/auth/login', [
            'email' => 'user_delete@example.com',
            'password' => 'StrongPass1',
        ])->assertStatus(200);

        $response = $this->deleteJson('/v1/company/'.$company->id, [], [
            'Authorization' => 'Bearer '.$loginResponse->json('data.accessToken'),
        ]);

        $response->assertStatus(403)->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_company_delete_endpoint_deletes_company_for_admin(): void
    {
        $admin = User::create([
            'name' => 'QA Admin Delete',
            'email' => 'qa.login@example.com',
            'password' => Hash::make('StrongPass1'),
        ]);

        $company = Company::create([
            'code' => 'delete_test_ok',
            'name' => 'Delete Test OK',
            'legal_name' => 'Delete Test OK Inc',
            'status' => 'active',
            'owner_user_id' => $admin->id,
            'timezone' => 'UTC',
            'currency' => 'USD',
            'country_code' => 'US',
        ]);

        $loginResponse = $this->postJson('/v1/identity/auth/login', [
            'email' => 'qa.login@example.com',
            'password' => 'StrongPass1',
        ])->assertStatus(200);

        $response = $this->deleteJson('/v1/company/'.$company->id, [], [
            'Authorization' => 'Bearer '.$loginResponse->json('data.accessToken'),
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('companies', [
            'id' => $company->id,
        ]);
    }
}

