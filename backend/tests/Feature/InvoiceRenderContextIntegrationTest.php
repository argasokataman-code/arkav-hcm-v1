<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceRenderContextIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_render_context_merges_business_company_profile_and_invoice_settings(): void
    {
        Setting::query()->updateOrCreate(
            ['key' => 'business_company_name'],
            ['value' => 'PT Arcav Integrasi', 'group' => 'business', 'type' => 'string']
        );

        Setting::query()->updateOrCreate(
            ['key' => 'business_email'],
            ['value' => 'billing@arcav.test', 'group' => 'business', 'type' => 'string']
        );

        Setting::query()->updateOrCreate(
            ['key' => 'business_phone'],
            ['value' => '+62-21-1111', 'group' => 'business', 'type' => 'string']
        );

        Setting::query()->updateOrCreate(
            ['key' => 'business_address'],
            ['value' => 'Jalan Integrasi No. 1', 'group' => 'business', 'type' => 'string']
        );

        $owner = User::query()->create([
            'name' => 'Owner Integrasi',
            'email' => 'owner-invoice-context@example.com',
            'password' => bcrypt('StrongPass1'),
        ]);

        $package = Package::query()->create([
            'code' => 'starter',
            'name' => 'Starter',
            'monthly_price' => 100000,
            'yearly_price' => 1000000,
            'billing_unit' => 'flat',
            'status' => 'active',
        ]);

        $company = Company::query()->create([
            'code' => 'CTX01',
            'name' => 'Company Context',
            'legal_name' => 'Company Context Legal',
            'status' => 'active',
            'owner_user_id' => $owner->id,
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $subscription = Subscription::query()->create([
            'company_id' => $company->id,
            'package_uuid' => $package->uuid,
            'plan_code' => $package->code,
            'status' => 'pending_payment',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'billing_cycle' => 'monthly',
            'amount' => 100000,
        ]);

        $invoice = Invoice::query()->create([
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'purchase_transaction_id' => null,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'amount_due' => 100000,
            'notes' => 'context integration test',
        ]);

        CompanySetting::query()->create([
            'company_id' => $company->id,
            'key' => 'company_profile_address',
            'value' => 'Jl. Profile 123',
            'type' => 'string',
        ]);

        CompanySetting::query()->create([
            'company_id' => $company->id,
            'key' => 'company_profile_city',
            'value' => 'Jakarta',
            'type' => 'string',
        ]);

        CompanySetting::query()->create([
            'company_id' => $company->id,
            'key' => 'company_profile_postal_code',
            'value' => '12950',
            'type' => 'string',
        ]);

        CompanySetting::query()->create([
            'company_id' => $company->id,
            'key' => 'invoice_prefix',
            'value' => 'CTX-',
            'type' => 'string',
        ]);

        CompanySetting::query()->create([
            'company_id' => $company->id,
            'key' => 'invoice_due_days',
            'value' => '45',
            'type' => 'string',
        ]);

        CompanySetting::query()->create([
            'company_id' => $company->id,
            'key' => 'invoice_header_terms',
            'value' => 'Mohon review tagihan sebelum jatuh tempo.',
            'type' => 'string',
        ]);

        CompanySetting::query()->create([
            'company_id' => $company->id,
            'key' => 'invoice_footer_terms',
            'value' => 'Pembayaran dianggap sah setelah dana diterima.',
            'type' => 'string',
        ]);

        CompanySetting::query()->create([
            'company_id' => $company->id,
            'key' => 'invoice_show_tax',
            'value' => '0',
            'type' => 'string',
        ]);

        CompanySetting::query()->create([
            'company_id' => $company->id,
            'key' => 'invoice_round_off_enabled',
            'value' => '1',
            'type' => 'string',
        ]);

        CompanySetting::query()->create([
            'company_id' => $company->id,
            'key' => 'invoice_round_off',
            'value' => 'round_up',
            'type' => 'string',
        ]);

        $context = app(InvoiceService::class)->resolveInvoiceRenderContext($invoice->fresh());

        $this->assertSame('PT Arcav Integrasi', $context['issuerProfile']['name']);
        $this->assertSame('billing@arcav.test', $context['issuerProfile']['email']);
        $this->assertSame('+62-21-1111', $context['issuerProfile']['phone']);
        $this->assertSame('Jalan Integrasi No. 1', $context['issuerProfile']['address']);

        $this->assertSame('Company Context', $context['companyProfile']['name']);
        $this->assertSame('Company Context Legal', $context['companyProfile']['legalName']);
        $this->assertSame('Jl. Profile 123', $context['companyProfile']['address']);
        $this->assertSame('Jakarta', $context['companyProfile']['city']);
        $this->assertSame('12950', $context['companyProfile']['postalCode']);

        $this->assertSame('CTX-', $context['invoiceDisplaySettings']['invoice_prefix']);
        $this->assertSame('45', $context['invoiceDisplaySettings']['invoice_due_days']);
        $this->assertSame('Mohon review tagihan sebelum jatuh tempo.', $context['invoiceDisplaySettings']['invoice_header_terms']);
        $this->assertSame('Pembayaran dianggap sah setelah dana diterima.', $context['invoiceDisplaySettings']['invoice_footer_terms']);
        $this->assertFalse((bool) $context['invoiceDisplaySettings']['invoice_show_tax']);
        $this->assertTrue((bool) $context['invoiceDisplaySettings']['invoice_round_off_enabled']);
        $this->assertSame('round_up', $context['invoiceDisplaySettings']['invoice_round_off']);
    }
}
