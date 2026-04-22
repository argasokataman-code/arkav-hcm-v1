<?php

namespace Tests\Feature;

use Carbon\Carbon;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Package;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicOnboardingApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Default: Turnstile disabled in tests unless a test explicitly enables it.
        config()->set('turnstile.enabled', false);
    }

    private function enableTurnstile(): void
    {
        config()->set('turnstile.enabled', true);
        config()->set('turnstile.site_key', '1x00000000000000000000AA');
        config()->set('turnstile.secret_key', '1x0000000000000000000000000000000AA');
        config()->set('turnstile.verify_url', 'https://challenges.cloudflare.com/turnstile/v0/siteverify');
    }

    public function test_turnstile_required_when_enabled(): void
    {
        $this->enableTurnstile();

        Http::fake([
            'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response(['success' => true], 200),
        ]);

        $package = Package::query()->create([
            'code' => 'starter',
            'name' => 'Starter',
            'monthly_price' => 100000,
            'yearly_price' => 1000000,
            'billing_unit' => 'flat',
            'status' => 'active',
        ]);

        $payload = [
            'package_uuid' => $package->uuid,
            'billing_cycle' => 'monthly',
            'company' => [
                'name' => 'Acme Inc',
                'timezone' => 'Asia/Jakarta',
                'currency' => 'IDR',
                'country_code' => 'ID',
                'address' => 'Jl. Sudirman Kav. 52-53',
                'city' => 'Jakarta Selatan',
            ],
            'owner' => [
                'name' => 'Budi Santoso',
                'email' => 'turnstile.required@example.com',
                'password' => 'StrongPass1',
                'confirmPassword' => 'StrongPass1',
            ],
        ];

        // Missing token
        $this->postJson('/v1/public/onboarding', $payload)
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        // With token
        $payload['turnstile_token'] = 'dummy-token';
        $this->postJson('/v1/public/onboarding', $payload)
            ->assertCreated()
            ->assertJsonPath('success', true);
    }

    public function test_inactive_package_is_rejected(): void
    {
        $package = Package::query()->create([
            'code' => 'starter',
            'name' => 'Starter',
            'monthly_price' => 100000,
            'yearly_price' => 1000000,
            'billing_unit' => 'flat',
            'status' => 'inactive',
        ]);

        $payload = [
            'package_uuid' => $package->uuid,
            'billing_cycle' => 'monthly',
            'company' => [
                'name' => 'Acme Inc',
                'timezone' => 'Asia/Jakarta',
                'currency' => 'IDR',
                'country_code' => 'ID',
            ],
            'owner' => [
                'name' => 'Budi Santoso',
                'email' => 'inactive.pkg.owner@example.com',
                'password' => 'StrongPass1',
                'confirmPassword' => 'StrongPass1',
            ],
        ];

        $this->postJson('/v1/public/onboarding', $payload)
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_duplicate_owner_email_is_rejected(): void
    {
        $package = Package::query()->create([
            'code' => 'starter',
            'name' => 'Starter',
            'monthly_price' => 100000,
            'yearly_price' => 1000000,
            'billing_unit' => 'flat',
            'status' => 'active',
        ]);

        \App\Models\User::query()->create([
            'name' => 'Existing',
            'email' => 'dupe.owner@example.com',
            'password' => bcrypt('StrongPass1'),
        ]);

        $payload = [
            'package_uuid' => $package->uuid,
            'billing_cycle' => 'monthly',
            'company' => [
                'name' => 'Acme Inc',
                'timezone' => 'Asia/Jakarta',
                'currency' => 'IDR',
                'country_code' => 'ID',
            ],
            'owner' => [
                'name' => 'Budi Santoso',
                'email' => 'dupe.owner@example.com',
                'password' => 'StrongPass1',
                'confirmPassword' => 'StrongPass1',
            ],
        ];

        $this->postJson('/v1/public/onboarding', $payload)
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_invalid_start_mode_is_rejected(): void
    {
        $package = Package::query()->create([
            'code' => 'starter',
            'name' => 'Starter',
            'monthly_price' => 100000,
            'yearly_price' => 1000000,
            'billing_unit' => 'flat',
            'status' => 'active',
        ]);

        $payload = [
            'package_uuid' => $package->uuid,
            'billing_cycle' => 'monthly',
            'start_mode' => 'free_forever',
            'company' => [
                'name' => 'Acme Inc',
                'timezone' => 'Asia/Jakarta',
                'currency' => 'IDR',
                'country_code' => 'ID',
            ],
            'owner' => [
                'name' => 'Budi Santoso',
                'email' => 'bad.mode.owner@example.com',
                'password' => 'StrongPass1',
                'confirmPassword' => 'StrongPass1',
            ],
        ];

        $this->postJson('/v1/public/onboarding', $payload)
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_guest_can_onboard_company_owner_and_trial_subscription(): void
    {
        $package = Package::query()->create([
            'code' => 'starter',
            'name' => 'Starter',
            'description' => null,
            'monthly_price' => 100000,
            'yearly_price' => 1000000,
            'billing_unit' => 'flat',
            'status' => 'active',
        ]);

        $payload = [
            'package_uuid' => $package->uuid,
            'billing_cycle' => 'monthly',
            'company' => [
                'name' => 'Acme Inc',
                'legal_name' => 'Acme Incorporated',
                'timezone' => 'Asia/Jakarta',
                'currency' => 'IDR',
                'country_code' => 'ID',
                'contact_phone' => '+62 812-3456-7890',
                'contact_person_name' => 'Siti',
                'contact_person_role' => 'HR Admin',
                'address' => 'Jl. Sudirman Kav. 52-53',
                'city' => 'Jakarta Selatan',
                'postal_code' => '12190',
            ],
            'owner' => [
                'name' => 'Budi Santoso',
                'email' => 'budi.santoso@example.com',
                'phone' => '+62 812-3456-7890',
                'password' => 'StrongPass1',
                'confirmPassword' => 'StrongPass1',
            ],
        ];

        $response = $this->postJson('/v1/public/onboarding', $payload)
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.company.code', fn ($v) => is_string($v) && $v !== '')
            ->assertJsonPath('data.subscription.status', 'trial');

        $companyCode = $response->json('data.company.code');
        $this->assertNotEmpty($companyCode);
        $this->assertDatabaseHas('companies', ['code' => $companyCode]);
        $company = Company::query()->where('code', $companyCode)->firstOrFail();
        $this->assertDatabaseHas('company_users', ['company_id' => $company->id, 'role' => 'owner', 'status' => 'active']);
        $this->assertDatabaseHas('subscriptions', ['company_id' => $company->id, 'status' => 'trial']);
        $this->assertDatabaseHas('company_settings', ['company_id' => $company->id, 'key' => 'business_phone', 'value' => '+62 812-3456-7890']);
        $this->assertDatabaseHas('company_settings', ['company_id' => $company->id, 'key' => 'owner_phone', 'value' => '+62 812-3456-7890']);
        $this->assertDatabaseHas('company_settings', ['company_id' => $company->id, 'key' => 'business_contact_name', 'value' => 'Siti']);
        $this->assertDatabaseHas('company_settings', ['company_id' => $company->id, 'key' => 'business_contact_role', 'value' => 'HR Admin']);
        $this->assertDatabaseHas('company_settings', ['company_id' => $company->id, 'key' => 'business_address', 'value' => 'Jl. Sudirman Kav. 52-53']);
        $this->assertDatabaseHas('company_settings', ['company_id' => $company->id, 'key' => 'business_city', 'value' => 'Jakarta Selatan']);
        $this->assertDatabaseHas('company_settings', ['company_id' => $company->id, 'key' => 'business_postal_code', 'value' => '12190']);
    }

    public function test_guest_can_onboard_and_start_pending_payment_with_invoice(): void
    {
        Queue::fake();

        $package = Package::query()->create([
            'code' => 'starter',
            'name' => 'Starter',
            'description' => null,
            'monthly_price' => 100000,
            'yearly_price' => 1000000,
            'billing_unit' => 'flat',
            'status' => 'active',
        ]);

        $payload = [
            'package_uuid' => $package->uuid,
            'billing_cycle' => 'monthly',
            'start_mode' => 'pending_payment',
            'billingEmail' => 'billing@acme.test',
            'company' => [
                'name' => 'Acme Paid Inc',
                'timezone' => 'Asia/Jakarta',
                'currency' => 'IDR',
                'country_code' => 'ID',
                'address' => 'Jl. Sudirman Kav. 52-53',
                'city' => 'Jakarta Selatan',
            ],
            'owner' => [
                'name' => 'Budi Santoso',
                'email' => 'budi.paid@example.com',
                'password' => 'StrongPass1',
                'confirmPassword' => 'StrongPass1',
            ],
        ];

        $response = $this->postJson('/v1/public/onboarding', $payload)
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.company.code', fn ($v) => is_string($v) && $v !== '')
            ->assertJsonPath('data.subscription.status', 'pending_payment');

        $this->assertNotNull($response->json('data.invoice.id'));

        $companyCode = $response->json('data.company.code');
        $company = Company::query()->where('code', $companyCode)->firstOrFail();
        $this->assertDatabaseHas('subscriptions', ['company_id' => $company->id, 'status' => 'pending_payment']);
        $this->assertDatabaseHas('invoices', ['company_id' => $company->id]);

        $subscription = \App\Models\Subscription::query()
            ->where('company_id', $company->id)
            ->latest('id')
            ->firstOrFail();
        $this->assertTrue(
            $subscription->ends_at->betweenIncluded(now()->addHours(23), now()->addHours(25)),
            'Pending payment window should default to around 24 hours after onboarding.'
        );

        $invoice = Invoice::query()
            ->where('company_id', $company->id)
            ->latest('id')
            ->firstOrFail();
        $this->assertSame(
            now()->addDay()->toDateString(),
            Carbon::parse($invoice->due_date)->toDateString(),
            'Pending payment invoice due date should default to next day.'
        );
    }

    public function test_duplicate_company_code_is_rejected(): void
    {
        $package = Package::query()->create([
            'code' => 'starter',
            'name' => 'Starter',
            'monthly_price' => 100000,
            'yearly_price' => 1000000,
            'billing_unit' => 'flat',
            'status' => 'active',
        ]);

        $owner = \App\Models\User::query()->create([
            'name' => 'Existing Owner',
            'email' => 'existing.owner@example.com',
            'password' => bcrypt('StrongPass1'),
        ]);

        Company::query()->create([
            'code' => 'acme_1',
            'name' => 'Existing',
            'legal_name' => null,
            'status' => 'active',
            'owner_user_id' => $owner->id,
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $payload = [
            'package_uuid' => $package->uuid,
            'billing_cycle' => 'monthly',
            'company' => [
                'code' => 'acme_1',
                'name' => 'Acme Inc',
                'timezone' => 'Asia/Jakarta',
                'currency' => 'IDR',
                'country_code' => 'ID',
            ],
            'owner' => [
                'name' => 'Budi Santoso',
                'email' => 'budi2@example.com',
                'password' => 'StrongPass1',
                'confirmPassword' => 'StrongPass1',
            ],
        ];

        $this->postJson('/v1/public/onboarding', $payload)
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }
}

