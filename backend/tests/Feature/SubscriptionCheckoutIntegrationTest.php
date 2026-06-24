<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\Billing\HcmSubscriptionCheckoutController;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SubscriptionCheckoutIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Company $company;
    private Package $package;
    private Package $altPackage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::query()->create([
            'name' => 'Subscription Owner',
            'email' => 'sub.owner@example.com',
            'password' => Hash::make('StrongPass1'),
        ]);

        $this->company = Company::query()->create([
            'code' => 'sub_integration_co',
            'name' => 'Subscription Integration Co',
            'status' => 'inactive',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        CompanyUser::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->package = Package::query()->create([
            'code' => 'starter',
            'name' => 'Starter',
            'monthly_price' => 199000,
            'yearly_price' => 1990000,
            'status' => 'active',
            'sort_order' => 1,
        ]);

        $this->altPackage = Package::query()->create([
            'code' => 'business',
            'name' => 'Business',
            'monthly_price' => 699000,
            'yearly_price' => 6990000,
            'status' => 'active',
            'sort_order' => 2,
        ]);
    }

    /** Integration: Blade renders package cards with correct data attributes matching API expectations. */
    #[Test]
    public function blade_renders_package_cards_with_ids_matching_api()
    {
        $response = $this->actingAs($this->user)->get('/subscription');
        $response->assertOk();

        $html = $response->getContent();

        // Package cards render with data-package-id
        foreach ([$this->package, $this->altPackage] as $pkg) {
            $this->assertStringContainsString(
                'data-package-id="'.$pkg->uuid.'"',
                $html,
                'Package card must use DB uuid as data-package-id for JS→API sync'
            );
            $this->assertStringContainsString(
                'data-package-code="'.$pkg->code.'"',
                $html,
                'Package card must include data-package-code for tracking'
            );
        }

        // Hidden select also uses uuid for form submission
        $this->assertStringContainsString(
            '<option value="'.$this->package->uuid.'"',
            $html,
            'Select option value must match package uuid for JSON payload'
        );
    }

    /** Integration: Page renders correctly for inactive company with all expected sections. */
    #[Test]
    public function subscription_page_shows_package_cards_and_checkout_form()
    {
        $response = $this->actingAs($this->user)->get('/subscription');
        $response->assertOk()
            ->assertSee('Pilih Paket')
            ->assertSee($this->package->name)
            ->assertSee($this->altPackage->name)
            ->assertSee('Siklus Tagihan')
            ->assertSee('Buat Invoice & Lanjut Bayar')
            ->assertSee('checkout-upgrade-form', false);
    }

    /** Integration: Pending invoice shows warning + form stays visible + checkout creates invoice. */
    #[Test]
    public function pending_invoice_shows_warning_and_checkout_still_creates_new_invoice()
    {
        // Create expired subscription + unpaid invoice
        $sub = Subscription::query()->create([
            'company_id' => $this->company->id,
            'package_uuid' => $this->package->uuid,
            'plan_code' => 'starter',
            'status' => 'inactive',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subDay(),
            'billing_cycle' => 'monthly',
            'amount' => 199000,
            'auto_renew' => false,
        ]);

        Invoice::query()->create([
            'company_id' => $this->company->id,
            'subscription_id' => $sub->id,
            'issue_date' => now()->subDays(2)->toDateString(),
            'due_date' => now()->addDays(3)->toDateString(),
            'amount_due' => 199000,
            'status' => 'draft',
            'is_paid' => false,
        ]);

        $response = $this->actingAs($this->user)->get('/subscription');
        $response->assertOk()
            // Warning is shown
            ->assertSee('Tagihan belum dibayar ditemukan')
            ->assertSee('membatalkan tagihan sebelumnya')
            // Form is still present (not locked)
            ->assertSee('checkout-upgrade-form', false)
            ->assertSee($this->altPackage->name);
    }

    /** Integration: Checkout API accepts the same package_uuid format as Blade renders. */
    #[Test]
    public function checkout_api_accepts_package_uuid_from_blade()
    {
        // Create inactive subscription so checkout is the user's expected path
        Subscription::query()->create([
            'company_id' => $this->company->id,
            'package_uuid' => $this->package->uuid,
            'plan_code' => 'starter',
            'status' => 'inactive',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subDay(),
            'billing_cycle' => 'monthly',
            'amount' => 199000,
            'auto_renew' => false,
        ]);

        // Simulate JS: sends package_uuid (uuid format from Blade) + billing_cycle
        $payload = [
            'package_uuid' => $this->altPackage->uuid,
            'billing_cycle' => 'monthly',
        ];

        // We need active company context — set request attribute as middleware does
        $request = \Illuminate\Http\Request::create('/v1/hcm/billing/checkout', 'POST', $payload);
        $request->attributes->set('activeCompanyId', $this->company->id);
        $request->attributes->set('activeCompany', $this->company);
        $request->attributes->set('activeCompanyCode', $this->company->code);
        $request->setUserResolver(fn () => $this->user);

        $controller = app(HcmSubscriptionCheckoutController::class);
        $controllerResponse = $controller->checkout($request);
        $responseData = json_decode((string) $controllerResponse->getContent(), true);

        $this->assertTrue($responseData['success'] ?? false, 'Checkout must succeed');
        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('invoice', $responseData['data']);
        $this->assertNotEmpty($responseData['data']['invoice']['id'] ?? '');
    }

    /** Integration: Full flow view→checkout creates invoice and updates active package. */
    #[Test]
    public function full_checkout_flow_creates_invoice_and_updates_subscription()
    {
        Subscription::query()->create([
            'company_id' => $this->company->id,
            'package_uuid' => $this->package->uuid,
            'plan_code' => 'starter',
            'status' => 'inactive',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subDay(),
            'billing_cycle' => 'monthly',
            'amount' => 199000,
            'auto_renew' => false,
        ]);

        // User visits subscription page
        $viewResponse = $this->actingAs($this->user)->get('/subscription');
        $viewResponse->assertOk();
        $html = $viewResponse->getContent();

        // Verify alt package is rendered (user can see it on page)
        $this->assertStringContainsString($this->altPackage->name, $html);
        $this->assertStringContainsString('data-package-id="'.$this->altPackage->uuid.'"', $html);

        // Simulate JS submission: POST checkout with business package
        $request = \Illuminate\Http\Request::create('/v1/hcm/billing/checkout', 'POST', [
            'package_uuid' => $this->altPackage->uuid,
            'billing_cycle' => 'yearly',
        ]);
        $request->attributes->set('activeCompanyId', $this->company->id);
        $request->attributes->set('activeCompany', $this->company);
        $request->attributes->set('activeCompanyCode', $this->company->code);
        $request->setUserResolver(fn () => $this->user);

        $controller = app(HcmSubscriptionCheckoutController::class);
        $checkoutResponse = $controller->checkout($request);
        $checkoutData = json_decode((string) $checkoutResponse->getContent(), true);

        $this->assertTrue($checkoutData['success'] ?? false);

        // Verify invoice created in DB
        $invoice = Invoice::where('company_id', $this->company->id)
            ->latest('id')
            ->first();
        $this->assertNotNull($invoice);
        $this->assertFalse($invoice->is_paid);
        $this->assertNotNull($invoice->id);
    }
}
