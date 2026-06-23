<?php

namespace Tests\Feature;

use App\Models\HcmBillingTaxPolicy;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\PackageAddon;
use App\Models\PurchaseTransaction;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class HcmAddonCheckoutFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_can_checkout_addon_with_separate_invoice_and_transaction(): void
    {
        $company = $this->createIsolatedTestCompany([
            'name' => 'Addon Checkout Co',
            'legal_name' => 'Addon Checkout Co Ltd',
        ]);

        $adminCtx = $this->createHcmAdminWithCompany([
            'email' => 'addon-checkout-admin@example.com',
            'password' => 'StrongPass1',
        ], $company);

        $package = Package::query()->create([
            'code' => 'enterprise',
            'name' => 'Enterprise',
            'monthly_price' => 1299000,
            'yearly_price' => 12990000,
            'billing_unit' => 'company',
            'status' => 'active',
            'is_global_admin_only' => false,
            'sort_order' => 1,
        ]);

        Subscription::query()->create([
            'company_id' => $company->id,
            'package_uuid' => $package->uuid,
            'plan_code' => 'enterprise',
            'status' => 'active',
            'starts_at' => now()->subDays(30),
            'ends_at' => now()->addMonth(),
            'billing_cycle' => 'monthly',
            'amount' => 1299000,
        ]);

        $addon = PackageAddon::query()->create([
            'code' => 'asset_management',
            'name' => 'Asset Management',
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
                    'subscription_tax_rate' => 7,
                    'addon_markup_rate' => 22,
                ],
                'global_rate_labels' => [
                    'addon_markup_rate' => 'Corporate tax',
                ],
            ], JSON_UNESCAPED_UNICODE),
        ]);

        $headers = [
            'Authorization' => 'Bearer '.$adminCtx['token'],
            'X-Company-Id' => (string) $company->id,
        ];

        $response = $this->withHeaders($headers)
            ->postJson('/v1/hcm/billing/addons/checkout', [
                'addon_id' => $addon->id,
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.reused', false)
            ->assertJsonPath('data.addon.code', 'asset_management')
            ->assertJsonPath('data.transaction.status', 'issued')
            ->assertJsonPath('data.invoice.status', 'draft')
            ->assertJsonPath('data.invoice.isPaid', false);

        $invoiceId = (int) $response->json('data.invoice.id');
        $transactionId = (int) $response->json('data.transaction.id');

        $this->assertDatabaseHas('purchase_transactions', [
            'id' => $transactionId,
            'company_id' => $company->id,
            'transaction_type' => 'addon',
            'package_addon_id' => $addon->id,
            'status' => 'issued',
        ]);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoiceId,
            'company_id' => $company->id,
            'purchase_transaction_id' => $transactionId,
            'is_paid' => 0,
            'status' => 'draft',
        ]);

        $invoice = Invoice::query()->findOrFail($invoiceId);
        $notes = json_decode((string) $invoice->notes, true);

        $this->assertSame('tenant_addon_checkout', $notes['source'] ?? null);
        $this->assertSame('asset_management', $notes['pricing_breakdown']['addon_code'] ?? null);
        $this->assertSame(49000.0, (float) ($notes['pricing_breakdown']['base_amount'] ?? 0));
        $this->assertSame(22.0, (float) ($notes['pricing_breakdown']['addon_tax_rate'] ?? 0));
        $this->assertSame(10780.0, (float) ($notes['pricing_breakdown']['addon_tax_amount'] ?? 0));
        $this->assertSame(59780.0, (float) ($notes['pricing_breakdown']['total_amount'] ?? 0));
    }

    public function test_addon_checkout_reuses_existing_unpaid_invoice(): void
    {
        $company = $this->createIsolatedTestCompany([
            'name' => 'Addon Reuse Co',
            'legal_name' => 'Addon Reuse Co Ltd',
        ]);

        $adminCtx = $this->createHcmAdminWithCompany([
            'email' => 'addon-reuse-admin@example.com',
            'password' => 'StrongPass1',
        ], $company);

        $package = Package::query()->create([
            'code' => 'enterprise',
            'name' => 'Enterprise',
            'monthly_price' => 1299000,
            'yearly_price' => 12990000,
            'billing_unit' => 'company',
            'status' => 'active',
            'is_global_admin_only' => false,
            'sort_order' => 1,
        ]);

        Subscription::query()->create([
            'company_id' => $company->id,
            'package_uuid' => $package->uuid,
            'plan_code' => 'enterprise',
            'status' => 'active',
            'starts_at' => now()->subDays(30),
            'ends_at' => now()->addMonth(),
            'billing_cycle' => 'monthly',
            'amount' => 1299000,
        ]);

        $addon = PackageAddon::query()->create([
            'code' => 'tickets',
            'name' => 'Tickets',
            'description' => 'Helpdesk add-on.',
            'price_per_unit' => 49000,
            'unit_name' => 'tenant / month',
            'status' => 'active',
        ]);

        $existingTransaction = PurchaseTransaction::query()->create([
            'transaction_code' => PurchaseTransaction::generateCode(),
            'company_id' => $company->id,
            'package_addon_id' => $addon->id,
            'transaction_type' => 'addon',
            'description' => 'Existing unpaid addon transaction',
            'amount' => 49000,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 49000,
            'status' => 'issued',
        ]);

        $existingInvoice = Invoice::query()->create([
            'company_id' => $company->id,
            'purchase_transaction_id' => $existingTransaction->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDay()->toDateString(),
            'amount_due' => 49000,
            'status' => 'sent',
            'is_paid' => false,
        ]);

        $headers = [
            'Authorization' => 'Bearer '.$adminCtx['token'],
            'X-Company-Id' => (string) $company->id,
        ];

        $beforeInvoiceCount = Invoice::query()->where('company_id', $company->id)->count();
        $beforeTransactionCount = PurchaseTransaction::query()->where('company_id', $company->id)->count();

        $this->withHeaders($headers)
            ->postJson('/v1/hcm/billing/addons/checkout', [
                'addon_id' => $addon->id,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.reused', true)
            ->assertJsonPath('data.invoice.id', $existingInvoice->id);

        $this->assertSame($beforeInvoiceCount, Invoice::query()->where('company_id', $company->id)->count());
        $this->assertSame($beforeTransactionCount, PurchaseTransaction::query()->where('company_id', $company->id)->count());
    }

    public function test_addon_checkout_does_not_reuse_unpaid_subscription_invoice(): void
    {
        $company = $this->createIsolatedTestCompany([
            'name' => 'Addon Separate Invoice Co',
            'legal_name' => 'Addon Separate Invoice Co Ltd',
        ]);

        $adminCtx = $this->createHcmAdminWithCompany([
            'email' => 'addon-separate-admin@example.com',
            'password' => 'StrongPass1',
        ], $company);

        $package = Package::query()->create([
            'code' => 'enterprise',
            'name' => 'Enterprise',
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
            'plan_code' => 'enterprise',
            'status' => 'active',
            'starts_at' => now()->subDays(3),
            'ends_at' => now()->addMonth(),
            'billing_cycle' => 'monthly',
            'amount' => 1299000,
            'auto_renew' => true,
        ]);

        $subscriptionInvoice = Invoice::query()->create([
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDay()->toDateString(),
            'amount_due' => 1299000,
            'status' => 'draft',
            'is_paid' => false,
            'notes' => json_encode([
                'source' => 'tenant_subscription_checkout',
                'pricing_breakdown' => ['total_amount' => 1299000],
            ], JSON_UNESCAPED_UNICODE),
        ]);

        $addon = PackageAddon::query()->create([
            'code' => 'tickets',
            'name' => 'Tickets',
            'description' => 'Helpdesk add-on.',
            'price_per_unit' => 49000,
            'unit_name' => 'tenant / month',
            'status' => 'active',
        ]);

        $headers = [
            'Authorization' => 'Bearer '.$adminCtx['token'],
            'X-Company-Id' => (string) $company->id,
        ];

        $response = $this->withHeaders($headers)
            ->postJson('/v1/hcm/billing/addons/checkout', [
                'addon_id' => $addon->id,
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.reused', false);

        $newAddonInvoiceId = (int) $response->json('data.invoice.id');
        $this->assertNotSame($subscriptionInvoice->id, $newAddonInvoiceId);
    }

    public function test_paying_addon_invoice_updates_subscription_amount_for_future_renewal(): void
    {
        $company = $this->createIsolatedTestCompany([
            'name' => 'Addon Renewal Co',
            'legal_name' => 'Addon Renewal Co Ltd',
        ]);

        $adminCtx = $this->createHcmAdminWithCompany([
            'email' => 'addon-renewal-admin@example.com',
            'password' => 'StrongPass1',
        ], $company);

        $package = Package::query()->create([
            'code' => 'enterprise',
            'name' => 'Enterprise',
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
            'plan_code' => 'enterprise',
            'status' => 'active',
            'starts_at' => now()->subDays(3),
            'ends_at' => now()->addMonth(),
            'billing_cycle' => 'monthly',
            'amount' => 1299000,
            'auto_renew' => true,
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
                    'subscription_tax_rate' => 7,
                    'addon_markup_rate' => 22,
                ],
                'global_rate_labels' => [
                    'addon_markup_rate' => 'Corporate tax',
                ],
            ], JSON_UNESCAPED_UNICODE),
        ]);

        $addon = PackageAddon::query()->create([
            'code' => 'asset_management',
            'name' => 'Asset Management',
            'description' => 'Asset lifecycle add-on.',
            'price_per_unit' => 49000,
            'unit_name' => 'tenant / month',
            'status' => 'active',
        ]);

        $headers = [
            'Authorization' => 'Bearer '.$adminCtx['token'],
            'X-Company-Id' => (string) $company->id,
        ];

        $checkoutResponse = $this->withHeaders($headers)
            ->postJson('/v1/hcm/billing/addons/checkout', [
                'addon_id' => $addon->id,
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $invoiceId = (int) $checkoutResponse->json('data.invoice.id');

        $this->withHeaders($headers)
            ->postJson('/v1/hcm/billing/invoices/'.$invoiceId.'/mock-pay')
            ->assertOk()
            ->assertJsonPath('success', true);

        $subscription->refresh();
        $this->assertSame(1358780.0, (float) $subscription->amount);

        $invoice = Invoice::query()->findOrFail($invoiceId);
        $transaction = PurchaseTransaction::query()->findOrFail((int) $invoice->purchase_transaction_id);
        $this->assertSame('paid', $transaction->status);
    }
}
