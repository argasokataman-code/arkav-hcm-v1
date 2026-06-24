<?php

namespace Tests\Feature;

use App\Models\HcmBillingTaxPolicy;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\PackageAddon;
use App\Models\PurchaseTransaction;
use App\Models\Subscription;
use App\Services\AddonRecurringSubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HcmAddonLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function createSetup(array $overrides = []): array
    {
        $company = $this->createIsolatedTestCompany([
            'name' => $overrides['company_name'] ?? 'Addon Lifecycle Co',
        ]);

        $adminCtx = $this->createHcmAdminWithCompany([
            'email' => 'addon-lifecycle-'.time().'@example.com',
            'password' => 'StrongPass1',
        ], $company);

        $package = Package::query()->create([
            'code' => $overrides['package_code'] ?? 'enterprise',
            'name' => $overrides['package_name'] ?? 'Enterprise',
            'description' => 'Enterprise package',
            'monthly_price' => 1299000,
            'yearly_price' => 12990000,
            'billing_unit' => 'company',
            'status' => 'active',
            'is_global_admin_only' => false,
            'sort_order' => 1,
        ]);

        $subscription = Subscription::query()->create([
            'company_id' => $company->id,
            'package_uuid' => $package->uuid,
            'plan_code' => $package->code,
            'status' => $overrides['sub_status'] ?? 'active',
            'starts_at' => now()->subDays(30),
            'ends_at' => $overrides['sub_ends_at'] ?? now()->addMonth(),
            'billing_cycle' => 'monthly',
            'amount' => 1299000,
            'auto_renew' => true,
        ]);

        $addon = PackageAddon::query()->create([
            'code' => $overrides['addon_code'] ?? 'asset_management',
            'name' => $overrides['addon_name'] ?? 'Asset Management',
            'description' => 'Asset lifecycle add-on.',
            'price_per_unit' => 49000,
            'unit_name' => 'tenant / month',
            'status' => 'active',
        ]);

        HcmBillingTaxPolicy::query()->create([
            'id' => (string) Str::uuid(),
            'company_id' => $company->id,
            'billing_month' => now()->format('Y-m'),
            'billing_cycle_type' => 'monthly',
            'tax_rate_percentage' => 0,
            'base_calculation_method' => 'invoice_amount_due',
            'effective_from' => now()->startOfMonth()->toDateString(),
            'status' => 'active',
            'notes' => json_encode([
                'global_rates' => [
                    'subscription_tax_rate' => 0,
                    'addon_markup_rate' => 0,
                ],
            ]),
        ]);

        return [
            'company' => $company,
            'adminCtx' => $adminCtx,
            'package' => $package,
            'subscription' => $subscription,
            'addon' => $addon,
        ];
    }

    private function headers(array $adminCtx): array
    {
        return [
            'Authorization' => 'Bearer '.$adminCtx['token'],
            'X-Company-Id' => (string) $adminCtx['company']->id,
        ];
    }

    #[Test]
    public function already_active_addon_blocks_on_active_subscription(): void
    {
        $s = $this->createSetup();
        $headers = $this->headers($s['adminCtx']);

        // Checkout addon
        $checkout = $this->withHeaders($headers)
            ->postJson('/v1/hcm/billing/addons/checkout', [
                'addon_id' => $s['addon']->id,
            ])->assertStatus(201);

        $invoiceId = $checkout->json('data.invoice.id');

        // Pay invoice
        $this->withHeaders($headers)
            ->postJson('/v1/hcm/billing/invoices/'.$invoiceId.'/mock-pay')
            ->assertOk();

        // Try buying same addon again → blocked
        $this->withHeaders($headers)
            ->postJson('/v1/hcm/billing/addons/checkout', [
                'addon_id' => $s['addon']->id,
            ])->assertStatus(409)
            ->assertJsonPath('error.code', 'ADDON_ALREADY_ACTIVE');
    }

    #[Test]
    public function already_active_addon_allows_repurchase_after_expiry(): void
    {
        $s = $this->createSetup();
        $headers = $this->headers($s['adminCtx']);

        // Checkout addon on active sub
        $checkout = $this->withHeaders($headers)
            ->postJson('/v1/hcm/billing/addons/checkout', [
                'addon_id' => $s['addon']->id,
            ])->assertStatus(201);

        $invoiceId = $checkout->json('data.invoice.id');

        // Pay
        $this->withHeaders($headers)
            ->postJson('/v1/hcm/billing/invoices/'.$invoiceId.'/mock-pay')
            ->assertOk();

        // Expire subscription
        $termService = app(\App\Services\SubscriptionTerminationService::class);
        $termService->terminateExpiredSubscription($s['subscription']);

        $s['subscription']->refresh();
        $this->assertSame('expired', $s['subscription']->status);

        // Check: addon transaction stays paid (available for restore on new sub)
        $this->assertDatabaseHas('purchase_transactions', [
            'company_id' => $s['company']->id,
            'package_addon_id' => $s['addon']->id,
            'transaction_type' => 'addon',
            'status' => 'paid',
        ]);

        // Create new subscription for same company
        $newPackage = Package::query()->create([
            'code' => 'starter',
            'name' => 'Starter',
            'description' => 'Starter package',
            'monthly_price' => 199000,
            'yearly_price' => 1990000,
            'billing_unit' => 'company',
            'status' => 'active',
            'is_global_admin_only' => false,
            'sort_order' => 2,
        ]);

        // Checkout new subscription
        $checkoutSub = $this->withHeaders($headers)
            ->postJson('/v1/hcm/billing/checkout', [
                'package_uuid' => $newPackage->uuid,
                'billing_cycle' => 'monthly',
            ])->assertStatus(201);

        $newSubId = $checkoutSub->json('data.subscription.id');

        // Pay subscription invoice
        $subInvoiceId = $checkoutSub->json('data.invoice.id');
        $this->withHeaders($headers)
            ->postJson('/v1/hcm/billing/invoices/'.$subInvoiceId.'/mock-pay')
            ->assertOk();

        // Addon was auto-restored to new subscription → already active
        $newSub = Subscription::query()->where('company_id', $s['company']->id)
            ->latest('id')->first();
        $this->assertNotNull($newSub);
        $expectedAmount = (float) $newSub->amount;
        $this->assertGreaterThan(
            199000.0,
            $expectedAmount,
            'Addon amount should be included in new subscription total'
        );

        // Verify addon amount included (199000 package + 49000 addon = 248000)
        $this->assertSame(248000.0, $expectedAmount);
    }

    #[Test]
    public function mark_as_paid_is_atomic_for_addon_invoice(): void
    {
        $s = $this->createSetup();
        $headers = $this->headers($s['adminCtx']);

        // Checkout addon
        $checkout = $this->withHeaders($headers)
            ->postJson('/v1/hcm/billing/addons/checkout', [
                'addon_id' => $s['addon']->id,
            ])->assertStatus(201);

        $invoiceId = $checkout->json('data.invoice.id');
        $txnId = $checkout->json('data.transaction.id');
        $originalAmount = $s['subscription']->amount;

        // Pay
        $this->withHeaders($headers)
            ->postJson('/v1/hcm/billing/invoices/'.$invoiceId.'/mock-pay')
            ->assertOk();

        // Verify atomic: invoice + transaction + subscription updated
        $this->assertDatabaseHas('invoices', [
            'id' => $invoiceId,
            'is_paid' => 1,
            'status' => 'paid',
        ]);

        $this->assertDatabaseHas('purchase_transactions', [
            'id' => $txnId,
            'status' => 'paid',
        ]);

        $s['subscription']->refresh();
        $this->assertGreaterThan(
            $originalAmount,
            (float) $s['subscription']->amount,
            'Subscription amount should include addon after payment'
        );
    }

    #[Test]
    public function termination_keeps_addon_transactions_paid_for_restore(): void
    {
        $s = $this->createSetup();
        $headers = $this->headers($s['adminCtx']);

        $checkout = $this->withHeaders($headers)
            ->postJson('/v1/hcm/billing/addons/checkout', [
                'addon_id' => $s['addon']->id,
            ])->assertStatus(201);

        $this->withHeaders($headers)
            ->postJson('/v1/hcm/billing/invoices/'.$checkout->json('data.invoice.id').'/mock-pay')
            ->assertOk();

        $this->assertDatabaseHas('purchase_transactions', [
            'company_id' => $s['company']->id,
            'package_addon_id' => $s['addon']->id,
            'status' => 'paid',
        ]);

        // Terminate subscription
        $termService = app(\App\Services\SubscriptionTerminationService::class);
        $termService->terminateExpiredSubscription($s['subscription']);

        // Addon transaction stays paid (available for restore)
        $this->assertDatabaseHas('purchase_transactions', [
            'company_id' => $s['company']->id,
            'package_addon_id' => $s['addon']->id,
            'status' => 'paid',
        ]);
    }

    #[Test]
    public function checkout_carries_over_addon_amount_from_old_paid_transactions(): void
    {
        $s = $this->createSetup();
        $headers = $this->headers($s['adminCtx']);

        // Checkout + pay addon on first subscription
        $checkout = $this->withHeaders($headers)
            ->postJson('/v1/hcm/billing/addons/checkout', [
                'addon_id' => $s['addon']->id,
            ])->assertStatus(201);

        $this->withHeaders($headers)
            ->postJson('/v1/hcm/billing/invoices/'.$checkout->json('data.invoice.id').'/mock-pay')
            ->assertOk();

        $s['subscription']->refresh();
        $amountWithAddon = (float) $s['subscription']->amount;

        // New subscription for same company
        $newPackage = Package::query()->create([
            'code' => 'ultimate',
            'name' => 'Ultimate',
            'monthly_price' => 19990000,
            'yearly_price' => 199900000,
            'billing_unit' => 'company',
            'status' => 'active',
            'is_global_admin_only' => false,
            'sort_order' => 0,
        ]);

        $checkoutSub = $this->withHeaders($headers)
            ->postJson('/v1/hcm/billing/checkout', [
                'package_uuid' => $newPackage->uuid,
                'billing_cycle' => 'monthly',
            ])->assertStatus(201);

        $newSubId = (int) $checkoutSub->json('data.subscription.id');
        $newSubInvoiceId = (int) $checkoutSub->json('data.invoice.id');

        // After restoreForSubscription: amount should include addon carry-over
        $newSub = Subscription::query()->findOrFail($newSubId);
        $expectedBase = 19990000.0;
        $this->assertSame(
            $expectedBase + 49000.0,
            (float) $newSub->amount,
            'Checkout should carry over addon amount from old paid transactions'
        );

        // Pay subscription invoice → activation should preserve addon amount
        $this->withHeaders($headers)
            ->postJson('/v1/hcm/billing/invoices/'.$newSubInvoiceId.'/mock-pay')
            ->assertOk();

        $newSub->refresh();
        $this->assertSame(
            $expectedBase + 49000.0,
            (float) $newSub->amount,
            'Activation should preserve addon amount'
        );
    }

    #[Test]
    public function restore_for_subscription_reapplies_addon_amount(): void
    {
        $s = $this->createSetup();
        $headers = $this->headers($s['adminCtx']);

        // Create paid addon on sub 1
        $checkout = $this->withHeaders($headers)
            ->postJson('/v1/hcm/billing/addons/checkout', [
                'addon_id' => $s['addon']->id,
            ])->assertStatus(201);

        $this->withHeaders($headers)
            ->postJson('/v1/hcm/billing/invoices/'.$checkout->json('data.invoice.id').'/mock-pay')
            ->assertOk();

        $s['subscription']->refresh();

        // Create new sub manually (simulating checkout without carry-over in amount)
        $newPackage = Package::query()->create([
            'code' => 'starter',
            'name' => 'Starter',
            'monthly_price' => 199000,
            'yearly_price' => 1990000,
            'billing_unit' => 'company',
            'status' => 'active',
            'is_global_admin_only' => false,
            'sort_order' => 2,
        ]);

        $newSub = Subscription::query()->create([
            'company_id' => $s['company']->id,
            'package_uuid' => $newPackage->uuid,
            'plan_code' => 'starter',
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'billing_cycle' => 'monthly',
            'amount' => 199000,
            'auto_renew' => true,
        ]);

        // Restore addon via service
        $service = app(AddonRecurringSubscriptionService::class);
        $service->restoreForSubscription($newSub);

        $newSub->refresh();
        $this->assertSame(
            248000.0,
            (float) $newSub->amount,
            'restoreForSubscription should add paid addon amounts'
        );
    }

    #[Test]
    public function checkout_addon_populates_billing_period(): void
    {
        $s = $this->createSetup();
        $headers = $this->headers($s['adminCtx']);

        $response = $this->withHeaders($headers)
            ->postJson('/v1/hcm/billing/addons/checkout', [
                'addon_id' => $s['addon']->id,
            ])->assertStatus(201);

        $txnId = (int) $response->json('data.transaction.id');

        $txn = PurchaseTransaction::query()->findOrFail($txnId);
        $this->assertNotNull($txn->billing_period_start, 'billing_period_start should be populated');
        $this->assertNotNull($txn->billing_period_end, 'billing_period_end should be populated');
        $this->assertSame(
            $s['subscription']->ends_at->toDateString(),
            $txn->billing_period_start->toDateString()
        );
    }

    #[Test]
    public function invoice_format_exposes_billing_period(): void
    {
        $s = $this->createSetup();
        $headers = $this->headers($s['adminCtx']);

        $response = $this->withHeaders($headers)
            ->postJson('/v1/hcm/billing/addons/checkout', [
                'addon_id' => $s['addon']->id,
            ])->assertStatus(201);

        $invoiceId = $response->json('data.invoice.id');

        $show = $this->withHeaders($headers)
            ->getJson('/v1/hcm/billing/invoices/'.$invoiceId)
            ->assertOk();

        $this->assertArrayHasKey('billingPeriodStart', $show->json('data'));
        $this->assertArrayHasKey('billingPeriodEnd', $show->json('data'));
    }

    #[Test]
    public function restore_for_subscription_dedup_by_package_addon(): void
    {
        $s = $this->createSetup();
        $headers = $this->headers($s['adminCtx']);

        // Pay addon once
        $r1 = $this->withHeaders($headers)
            ->postJson('/v1/hcm/billing/addons/checkout', [
                'addon_id' => $s['addon']->id,
            ])->assertStatus(201);
        $this->withHeaders($headers)
            ->postJson('/v1/hcm/billing/invoices/'.$r1->json('data.invoice.id').'/mock-pay')
            ->assertOk();

        $initialAmount = (float) $s['subscription']->fresh()->amount;

        // Create DUPLICATE paid transaction for same addon (simulate race/bug)
        $dup = PurchaseTransaction::query()->create([
            'transaction_code' => PurchaseTransaction::generateCode(),
            'company_id' => $s['company']->id,
            'subscription_id' => $s['subscription']->id,
            'package_addon_id' => $s['addon']->id,
            'transaction_type' => 'addon',
            'description' => 'Duplicate addon',
            'amount' => 49000,
            'tax_amount' => 0,
            'total_amount' => 49000,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        // Create new subscription
        $newPkg = Package::query()->create([
            'code' => 'starter',
            'name' => 'Starter',
            'monthly_price' => 199000,
            'yearly_price' => 1990000,
            'billing_unit' => 'company',
            'status' => 'active',
            'is_global_admin_only' => false,
            'sort_order' => 2,
        ]);
        $newSub = Subscription::query()->create([
            'company_id' => $s['company']->id,
            'package_uuid' => $newPkg->uuid,
            'plan_code' => 'starter',
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'billing_cycle' => 'monthly',
            'amount' => 199000,
            'auto_renew' => true,
        ]);

        // Restore — should apply addon ONCE only
        $service = app(AddonRecurringSubscriptionService::class);
        $service->restoreForSubscription($newSub);

        $newSub->refresh();
        $this->assertSame(
            248000.0,
            (float) $newSub->amount,
            'restoreForSubscription should dedup addon — amount should be 199000 + 49000 = 248000, not 297000'
        );
    }

    #[Test]
    public function renewal_excludes_inactive_addon_from_amount(): void
    {
        $s = $this->createSetup(['sub_ends_at' => now()->startOfDay()]);
        $headers = $this->headers($s['adminCtx']);

        // Pay addon
        $r = $this->withHeaders($headers)
            ->postJson('/v1/hcm/billing/addons/checkout', [
                'addon_id' => $s['addon']->id,
            ])->assertStatus(201);
        $this->withHeaders($headers)
            ->postJson('/v1/hcm/billing/invoices/'.$r->json('data.invoice.id').'/mock-pay')
            ->assertOk();

        $s['subscription']->refresh();
        $this->assertGreaterThan(1299000.0, (float) $s['subscription']->amount);

        // Deactivate the addon
        $s['addon']->update(['status' => 'inactive']);

        // Run renewal job
        $job = new \App\Jobs\SubscriptionRenewalNotifier();
        $job->handle();

        // Check the renewal invoice — addon amount should be excluded
        $invoice = Invoice::query()
            ->where('company_id', $s['company']->id)
            ->where('subscription_id', $s['subscription']->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($invoice, 'Renewal invoice should exist');
        $this->assertSame(1299000.0, (float) $invoice->amount_due,
            'Renewal invoice should exclude inactive addon amount');

        // Notes should contain inactive_addons_removed
        $notes = json_decode((string) $invoice->notes, true);
        $this->assertArrayHasKey('inactive_addons_removed', $notes,
            'Notes should document inactive addon removal');
        $this->assertSame($s['addon']->code, $notes['inactive_addons_removed']['addon_codes'][0] ?? null);
    }

    #[Test]
    public function cancel_addon_removes_amount_and_updates_subscription(): void
    {
        $s = $this->createSetup();
        $headers = $this->headers($s['adminCtx']);

        $r = $this->withHeaders($headers)
            ->postJson('/v1/hcm/billing/addons/checkout', [
                'addon_id' => $s['addon']->id,
            ])->assertStatus(201);
        $this->withHeaders($headers)
            ->postJson('/v1/hcm/billing/invoices/'.$r->json('data.invoice.id').'/mock-pay')
            ->assertOk();

        $s['subscription']->refresh();
        $amountWithAddon = (float) $s['subscription']->amount;
        $this->assertGreaterThan(1299000.0, $amountWithAddon);

        // Cancel addon
        $this->withHeaders($headers)
            ->postJson('/v1/hcm/billing/addons/cancel', [
                'addon_id' => $s['addon']->id,
            ])->assertOk()
            ->assertJsonPath('data.effective', 'next_billing_cycle')
            ->assertJsonPath('data.newAmount', 1299000);

        $s['subscription']->refresh();
        $this->assertSame(1299000.0, (float) $s['subscription']->amount,
            'Cancel addon should remove addon amount from subscription');

        // Transaction should be cancelled
        $this->assertDatabaseHas('purchase_transactions', [
            'company_id' => $s['company']->id,
            'package_addon_id' => $s['addon']->id,
            'status' => 'cancelled',
        ]);
    }

    #[Test]
    public function cancel_addon_returns_404_for_inactive_addon(): void
    {
        $s = $this->createSetup();
        $headers = $this->headers($s['adminCtx']);

        $this->withHeaders($headers)
            ->postJson('/v1/hcm/billing/addons/cancel', [
                'addon_id' => 99999,
            ])->assertStatus(422);
    }

    #[Test]
    public function trial_subscription_cannot_buy_addon(): void
    {
        $s = $this->createSetup(['sub_status' => 'trial']);
        $headers = $this->headers($s['adminCtx']);

        $this->withHeaders($headers)
            ->postJson('/v1/hcm/billing/addons/checkout', [
                'addon_id' => $s['addon']->id,
            ])->assertStatus(422)
            ->assertJsonPath('error.code', 'NO_ACTIVE_SUBSCRIPTION');
    }

    #[Test]
    public function no_subscription_cannot_buy_addon(): void
    {
        $company = $this->createIsolatedTestCompany();
        $adminCtx = $this->createHcmAdminWithCompany([
            'email' => 'no-sub-'.time().'@example.com',
            'password' => 'StrongPass1',
        ], $company);

        $addon = PackageAddon::query()->create([
            'code' => 'asset_management',
            'name' => 'Asset Management',
            'price_per_unit' => 49000,
            'unit_name' => 'tenant / month',
            'status' => 'active',
        ]);

        $headers = [
            'Authorization' => 'Bearer '.$adminCtx['token'],
            'X-Company-Id' => (string) $company->id,
        ];

        $this->withHeaders($headers)
            ->postJson('/v1/hcm/billing/addons/checkout', [
                'addon_id' => $addon->id,
            ])->assertStatus(422)
            ->assertJsonPath('error.code', 'NO_ACTIVE_SUBSCRIPTION');
    }
}
