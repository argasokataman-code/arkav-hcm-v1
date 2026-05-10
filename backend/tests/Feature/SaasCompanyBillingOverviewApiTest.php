<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceEmailLog;
use App\Models\Package;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaasCompanyBillingOverviewApiTest extends TestCase
{
    use RefreshDatabase;

    private string $adminToken;
    private string $userToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Admin User',
            'email' => 'qa.login@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ]);

        $adminLogin = $this->postJson('/v1/identity/auth/login', [
            'email' => 'qa.login@example.com',
            'password' => 'StrongPass1',
        ]);
        $this->adminToken = (string) $adminLogin->json('data.accessToken');

        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Regular User',
            'email' => 'user@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ]);

        $userLogin = $this->postJson('/v1/identity/auth/login', [
            'email' => 'user@example.com',
            'password' => 'StrongPass1',
        ]);
        $this->userToken = (string) $userLogin->json('data.accessToken');
    }

    private function adminRequest()
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->adminToken);
    }

    private function userRequest()
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->userToken);
    }

    public function test_non_admin_cannot_access_billing_overview(): void
    {
        $this->userRequest()
            ->getJson('/v1/saas/companies/billing-overview?tab=trial')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'ADMIN_REQUIRED');
    }

    public function test_admin_can_list_trial_and_subscribed_tabs_with_invoice_email_status(): void
    {
        $package = Package::query()->create([
            'code' => 'starter',
            'name' => 'Starter',
            'monthly_price' => 100000,
            'yearly_price' => 1000000,
            'billing_unit' => 'flat',
            'status' => 'active',
        ]);

        $trialCompany = Company::query()->create([
            'code' => 'TRIAL01',
            'name' => 'Trial Co',
            'legal_name' => null,
            'status' => 'active',
            'owner_user_id' => 1,
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        Subscription::query()->create([
            'company_id' => $trialCompany->id,
            'package_uuid' => $package->uuid,
            'plan_code' => $package->code,
            'status' => 'trial',
            'starts_at' => now()->subDays(1),
            'ends_at' => now()->addMonth(),
            'trial_ends_at' => now()->addDays(29),
            'billing_cycle' => 'monthly',
            'amount' => 0,
        ]);

        $paidCompany = Company::query()->create([
            'code' => 'PAID01',
            'name' => 'Paid Co',
            'legal_name' => null,
            'status' => 'active',
            'owner_user_id' => 1,
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $paidSub = Subscription::query()->create([
            'company_id' => $paidCompany->id,
            'package_uuid' => $package->uuid,
            'plan_code' => $package->code,
            'status' => 'active',
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->addMonth(),
            'trial_ends_at' => null,
            'billing_cycle' => 'monthly',
            'amount' => 100000,
        ]);

        $invoice = Invoice::query()->create([
            'company_id' => $paidCompany->id,
            'subscription_id' => $paidSub->id,
            'purchase_transaction_id' => null,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'amount_due' => 100000,
            'notes' => null,
        ]);

        InvoiceEmailLog::query()->create([
            'invoice_id' => $invoice->id,
            'to_email' => 'billing@paid.co',
            'status' => 'sent',
            'provider_message_id' => 'msg-1',
            'error_message' => null,
        ]);

        $suspendedCompany = Company::query()->create([
            'code' => 'SUSP01',
            'name' => 'Suspended Co',
            'legal_name' => null,
            'status' => 'active',
            'owner_user_id' => 1,
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        Subscription::query()->create([
            'company_id' => $suspendedCompany->id,
            'package_uuid' => $package->uuid,
            'plan_code' => $package->code,
            'status' => 'suspended',
            'starts_at' => now()->subDays(30),
            'ends_at' => now()->addDays(7),
            'trial_ends_at' => null,
            'billing_cycle' => 'monthly',
            'amount' => 100000,
        ]);

        $trial = $this->adminRequest()
            ->getJson('/v1/saas/companies/billing-overview?tab=trial')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertCount(1, $trial->json('data'));
        $this->assertSame('TRIAL01', $trial->json('data.0.company.code'));
        $this->assertSame('trial', $trial->json('data.0.subscription.status'));
        $this->assertSame('no_invoice', $trial->json('data.0.email.status'));

        $subscribed = $this->adminRequest()
            ->getJson('/v1/saas/companies/billing-overview?tab=subscribed')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertCount(2, $subscribed->json('data'));

        $rows = collect($subscribed->json('data'));
        $paidRow = $rows->firstWhere('company.code', 'PAID01');
        $suspendedRow = $rows->firstWhere('company.code', 'SUSP01');

        $this->assertNotNull($paidRow);
        $this->assertSame('active', $paidRow['subscription']['status'] ?? null);
        $this->assertSame('sent', $paidRow['email']['status'] ?? null);
        $this->assertNotNull($paidRow['latestInvoice']['id'] ?? null);

        $this->assertNotNull($suspendedRow);
        $this->assertSame('suspended', $suspendedRow['subscription']['status'] ?? null);
        $this->assertSame('no_invoice', $suspendedRow['email']['status'] ?? null);
    }

    public function test_dashboard_uses_latest_subscription_once_per_company(): void
    {
        $starter = Package::query()->create([
            'code' => 'starter',
            'name' => 'Starter',
            'monthly_price' => 100000,
            'yearly_price' => 1000000,
            'billing_unit' => 'flat',
            'status' => 'active',
        ]);

        $pro = Package::query()->create([
            'code' => 'pro',
            'name' => 'Pro',
            'monthly_price' => 200000,
            'yearly_price' => 2000000,
            'billing_unit' => 'flat',
            'status' => 'active',
        ]);

        $company = Company::query()->create([
            'code' => 'MULTI01',
            'name' => 'Multi Sub Co',
            'legal_name' => null,
            'status' => 'active',
            'owner_user_id' => 1,
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $oldSubscription = Subscription::query()->create([
            'company_id' => $company->id,
            'package_uuid' => $starter->uuid,
            'plan_code' => $starter->code,
            'status' => 'trial',
            'starts_at' => now()->subDays(40),
            'ends_at' => now()->subDays(10),
            'trial_ends_at' => now()->subDays(11),
            'billing_cycle' => 'monthly',
            'amount' => 0,
        ]);

        Invoice::query()->create([
            'company_id' => $company->id,
            'subscription_id' => $oldSubscription->id,
            'purchase_transaction_id' => null,
            'issue_date' => now()->subDays(20)->toDateString(),
            'due_date' => now()->subDays(13)->toDateString(),
            'amount_due' => 100000,
            'notes' => null,
        ]);

        $latestSubscription = Subscription::query()->create([
            'company_id' => $company->id,
            'package_uuid' => $pro->uuid,
            'plan_code' => $pro->code,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'trial_ends_at' => null,
            'billing_cycle' => 'monthly',
            'amount' => 200000,
        ]);

        $latestInvoice = Invoice::query()->create([
            'company_id' => $company->id,
            'subscription_id' => $latestSubscription->id,
            'purchase_transaction_id' => null,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'amount_due' => 200000,
            'notes' => null,
        ]);

        $response = $this->adminRequest()
            ->getJson('/v1/saas/companies/billing-overview?tab=subscribed')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertCount(1, $response->json('data'));
        $this->assertSame('MULTI01', $response->json('data.0.company.code'));
        $this->assertSame($latestSubscription->id, $response->json('data.0.subscription.id'));
        $this->assertSame('pro', $response->json('data.0.subscription.planCode'));
        $this->assertSame($latestInvoice->id, $response->json('data.0.latestInvoice.id'));
    }

    public function test_dashboard_returns_state_badges_and_detail_url_for_pending_payment_mismatches(): void
    {
        $package = Package::query()->create([
            'code' => 'starter',
            'name' => 'Starter',
            'monthly_price' => 100000,
            'yearly_price' => 1000000,
            'billing_unit' => 'flat',
            'status' => 'active',
        ]);

        $missingInvoiceCompany = Company::query()->create([
            'code' => 'MISS01',
            'name' => 'Missing Invoice Co',
            'legal_name' => null,
            'status' => 'active',
            'owner_user_id' => 1,
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        Subscription::query()->create([
            'company_id' => $missingInvoiceCompany->id,
            'package_uuid' => $package->uuid,
            'plan_code' => $package->code,
            'status' => 'pending_payment',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(7),
            'billing_cycle' => 'monthly',
            'amount' => 100000,
        ]);

        $mismatchCompany = Company::query()->create([
            'code' => 'MISMATCH01',
            'name' => 'Mismatch Co',
            'legal_name' => null,
            'status' => 'active',
            'owner_user_id' => 1,
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $mismatchSubscription = Subscription::query()->create([
            'company_id' => $mismatchCompany->id,
            'package_uuid' => $package->uuid,
            'plan_code' => $package->code,
            'status' => 'pending_payment',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(7),
            'billing_cycle' => 'monthly',
            'amount' => 100000,
        ]);

        $paidInvoice = Invoice::query()->create([
            'company_id' => $mismatchCompany->id,
            'subscription_id' => $mismatchSubscription->id,
            'purchase_transaction_id' => null,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'amount_due' => 100000,
            'is_paid' => true,
            'paid_date' => now(),
            'status' => 'paid',
            'notes' => null,
        ]);

        $response = $this->adminRequest()
            ->getJson('/v1/saas/companies/billing-overview?tab=subscribed&per_page=50')
            ->assertOk()
            ->assertJsonPath('success', true);

        $rows = collect($response->json('data'));
        $missingInvoiceRow = $rows->firstWhere('company.code', 'MISS01');
        $mismatchRow = $rows->firstWhere('company.code', 'MISMATCH01');

        $this->assertNotNull($missingInvoiceRow);
        $this->assertSame('INVOICE_MISSING', $missingInvoiceRow['stateBadges'][0]['code'] ?? null);

        $this->assertNotNull($mismatchRow);
        $this->assertSame('STATE_MISMATCH', $mismatchRow['stateBadges'][0]['code'] ?? null);
        $this->assertSame(url('/saas/billing-overview/invoices/'.$paidInvoice->uuid), $mismatchRow['latestInvoice']['detailUrl'] ?? null);
    }

    public function test_dashboard_falls_back_to_company_latest_invoice_when_subscription_invoice_is_not_linked(): void
    {
        $package = Package::query()->create([
            'code' => 'growth',
            'name' => 'Growth',
            'monthly_price' => 250000,
            'yearly_price' => 2500000,
            'billing_unit' => 'flat',
            'status' => 'active',
        ]);

        $company = Company::query()->create([
            'code' => 'FALLBACK01',
            'name' => 'Fallback Co',
            'legal_name' => null,
            'status' => 'active',
            'owner_user_id' => 1,
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        Subscription::query()->create([
            'company_id' => $company->id,
            'package_uuid' => $package->uuid,
            'plan_code' => $package->code,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'trial_ends_at' => null,
            'billing_cycle' => 'monthly',
            'amount' => 250000,
        ]);

        $fallbackInvoice = Invoice::query()->create([
            'company_id' => $company->id,
            'subscription_id' => null,
            'purchase_transaction_id' => null,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'amount_due' => 250000,
            'status' => 'draft',
            'notes' => 'Legacy invoice without subscription relation',
        ]);

        $response = $this->adminRequest()
            ->getJson('/v1/saas/companies/billing-overview?tab=subscribed')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertCount(1, $response->json('data'));
        $this->assertSame('FALLBACK01', $response->json('data.0.company.code'));
        $this->assertSame($fallbackInvoice->id, $response->json('data.0.latestInvoice.id'));
        $this->assertSame(url('/saas/billing-overview/invoices/'.$fallbackInvoice->uuid), $response->json('data.0.latestInvoice.detailUrl'));
    }

    public function test_subscribed_tab_includes_company_with_invoice_even_without_subscription_row(): void
    {
        $company = Company::query()->create([
            'code' => 'INVOICEONLY01',
            'name' => 'Invoice Only Co',
            'legal_name' => null,
            'status' => 'active',
            'owner_user_id' => 1,
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $invoice = Invoice::query()->create([
            'company_id' => $company->id,
            'subscription_id' => null,
            'purchase_transaction_id' => null,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'amount_due' => 300000,
            'status' => 'paid',
            'is_paid' => true,
            'paid_date' => now(),
            'notes' => 'Legacy paid invoice only',
        ]);

        $response = $this->adminRequest()
            ->getJson('/v1/saas/companies/billing-overview?tab=subscribed')
            ->assertOk()
            ->assertJsonPath('success', true);

        $rows = collect($response->json('data'));
        $invoiceOnlyRow = $rows->firstWhere('company.code', 'INVOICEONLY01');

        $this->assertNotNull($invoiceOnlyRow);
        $this->assertNull($invoiceOnlyRow['subscription']['id'] ?? null);
        $this->assertSame($invoice->id, $invoiceOnlyRow['latestInvoice']['id'] ?? null);
        $this->assertSame('not_sent', $invoiceOnlyRow['email']['status'] ?? null);
    }

    public function test_trial_company_with_invoice_stays_in_trial_tab(): void
    {
        $package = Package::query()->create([
            'code' => 'trial-plan',
            'name' => 'Trial Plan',
            'monthly_price' => 100000,
            'yearly_price' => 1000000,
            'billing_unit' => 'flat',
            'status' => 'active',
        ]);

        $company = Company::query()->create([
            'code' => 'TRIALINV01',
            'name' => 'Trial Invoice Co',
            'legal_name' => null,
            'status' => 'active',
            'owner_user_id' => 1,
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $trialSubscription = Subscription::query()->create([
            'company_id' => $company->id,
            'package_uuid' => $package->uuid,
            'plan_code' => $package->code,
            'status' => 'trial',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(29),
            'trial_ends_at' => now()->addDays(29),
            'billing_cycle' => 'monthly',
            'amount' => 0,
        ]);

        $invoice = Invoice::query()->create([
            'company_id' => $company->id,
            'subscription_id' => $trialSubscription->id,
            'purchase_transaction_id' => null,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'amount_due' => 0,
            'status' => 'draft',
            'is_paid' => false,
            'notes' => 'Trial invoice preview',
        ]);

        $trialResponse = $this->adminRequest()
            ->getJson('/v1/saas/companies/billing-overview?tab=trial')
            ->assertOk()
            ->assertJsonPath('success', true);

        $trialRows = collect($trialResponse->json('data'));
        $trialRow = $trialRows->firstWhere('company.code', 'TRIALINV01');

        $this->assertNotNull($trialRow);
        $this->assertSame('trial', $trialRow['subscription']['status'] ?? null);
        $this->assertSame($invoice->id, $trialRow['latestInvoice']['id'] ?? null);
        $this->assertSame('not_sent', $trialRow['email']['status'] ?? null);

        $subscribedResponse = $this->adminRequest()
            ->getJson('/v1/saas/companies/billing-overview?tab=subscribed')
            ->assertOk()
            ->assertJsonPath('success', true);

        $subscribedRows = collect($subscribedResponse->json('data'));
        $this->assertNull($subscribedRows->firstWhere('company.code', 'TRIALINV01'));
    }

    public function test_overview_exposes_cancellation_metadata_and_extended_state_badges(): void
    {
        $package = Package::query()->create([
            'code' => 'growth-plan',
            'name' => 'Growth Plan',
            'monthly_price' => 200000,
            'yearly_price' => 2000000,
            'billing_unit' => 'flat',
            'status' => 'active',
        ]);

        $cancelledCompany = Company::query()->create([
            'code' => 'CANCEL01',
            'name' => 'Cancelled Trial Co',
            'legal_name' => null,
            'status' => 'active',
            'owner_user_id' => 1,
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $cancelledSubscription = Subscription::query()->create([
            'company_id' => $cancelledCompany->id,
            'package_uuid' => $package->uuid,
            'plan_code' => $package->code,
            'status' => 'cancelled',
            'starts_at' => now()->subDays(35),
            'ends_at' => now()->subDays(5),
            'trial_ends_at' => now()->subDays(5),
            'billing_cycle' => 'monthly',
            'amount' => 0,
        ]);

        $cancelledInvoice = Invoice::query()->create([
            'company_id' => $cancelledCompany->id,
            'subscription_id' => $cancelledSubscription->id,
            'purchase_transaction_id' => null,
            'issue_date' => now()->subDays(10)->toDateString(),
            'due_date' => now()->subDays(7)->toDateString(),
            'amount_due' => 200000,
            'status' => 'draft',
            'is_paid' => false,
            'notes' => 'Cancelled after trial ended',
        ]);

        $pendingCompany = Company::query()->create([
            'code' => 'OVERDUE01',
            'name' => 'Pending Overdue Co',
            'legal_name' => null,
            'status' => 'active',
            'owner_user_id' => 1,
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $pendingSubscription = Subscription::query()->create([
            'company_id' => $pendingCompany->id,
            'package_uuid' => $package->uuid,
            'plan_code' => $package->code,
            'status' => 'pending_payment',
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->addDays(20),
            'billing_cycle' => 'monthly',
            'amount' => 200000,
        ]);

        Invoice::query()->create([
            'company_id' => $pendingCompany->id,
            'subscription_id' => $pendingSubscription->id,
            'purchase_transaction_id' => null,
            'issue_date' => now()->subDays(8)->toDateString(),
            'due_date' => now()->subDays(2)->toDateString(),
            'amount_due' => 200000,
            'status' => 'sent',
            'is_paid' => false,
            'notes' => 'Pending overdue payment',
        ]);

        $response = $this->adminRequest()
            ->getJson('/v1/saas/companies/billing-overview?tab=subscribed&per_page=50')
            ->assertOk()
            ->assertJsonPath('success', true);

        $rows = collect($response->json('data'));
        $cancelledRow = $rows->firstWhere('company.code', 'CANCEL01');
        $overdueRow = $rows->firstWhere('company.code', 'OVERDUE01');

        $this->assertNotNull($cancelledRow);
        $this->assertSame('trial_expired', $cancelledRow['subscription']['cancellationReason'] ?? null);
        $this->assertNotEmpty($cancelledRow['subscription']['cancellationDescription'] ?? null);
        $this->assertNotEmpty($cancelledRow['subscription']['cancelledAt'] ?? null);
        $this->assertContains('CANCELLED_TRIAL_EXPIRED', collect($cancelledRow['stateBadges'] ?? [])->pluck('code')->all());

        $this->assertNotNull($overdueRow);
        $this->assertContains('PAYMENT_OVERDUE', collect($overdueRow['stateBadges'] ?? [])->pluck('code')->all());

        $this->adminRequest()
            ->getJson('/v1/saas/invoices/'.$cancelledInvoice->uuid)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.subscription.cancellationReason', 'trial_expired');
    }

    public function test_admin_can_preview_invoice_pdf_inline(): void
    {
        $package = Package::query()->create([
            'code' => 'preview-plan',
            'name' => 'Preview Plan',
            'monthly_price' => 100000,
            'yearly_price' => 1000000,
            'billing_unit' => 'flat',
            'status' => 'active',
        ]);

        $company = Company::query()->create([
            'code' => 'PDFPRVW01',
            'name' => 'Preview Company',
            'legal_name' => null,
            'status' => 'active',
            'owner_user_id' => 1,
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $subscription = Subscription::query()->create([
            'company_id' => $company->id,
            'package_uuid' => $package->uuid,
            'plan_code' => $package->code,
            'status' => 'active',
            'starts_at' => now()->subDays(5),
            'ends_at' => now()->addDays(25),
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
            'status' => 'sent',
            'is_paid' => false,
            'notes' => 'Preview PDF endpoint test',
        ]);

        $response = $this->adminRequest()
            ->get('/v1/saas/invoices/'.$invoice->uuid.'/pdf/preview')
            ->assertOk();

        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('inline;', (string) $response->headers->get('content-disposition', ''));
    }

    public function test_cancelled_subscription_without_reason_uses_unknown_reason(): void
    {
        $package = Package::query()->create([
            'code' => 'cancel-unknown-plan',
            'name' => 'Cancel Unknown Plan',
            'monthly_price' => 300000,
            'yearly_price' => 3000000,
            'billing_unit' => 'flat',
            'status' => 'active',
        ]);

        $company = Company::query()->create([
            'code' => 'CANCELUNK01',
            'name' => 'Cancel Unknown Co',
            'legal_name' => null,
            'status' => 'active',
            'owner_user_id' => 1,
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        Subscription::query()->create([
            'company_id' => $company->id,
            'package_uuid' => $package->uuid,
            'plan_code' => $package->code,
            'status' => 'cancelled',
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subDays(1),
            'trial_ends_at' => null,
            'billing_cycle' => 'yearly',
            'amount' => 3000000,
            'termination_reason' => null,
            'terminated_at' => now()->subDays(1),
        ]);

        $response = $this->adminRequest()
            ->getJson('/v1/saas/companies/billing-overview?tab=subscribed&per_page=50')
            ->assertOk()
            ->assertJsonPath('success', true);

        $rows = collect($response->json('data'));
        $row = $rows->firstWhere('company.code', 'CANCELUNK01');

        $this->assertNotNull($row);
        $this->assertSame('unknown', $row['subscription']['cancellationReason'] ?? null);
        $this->assertNotEmpty($row['subscription']['cancellationDescription'] ?? null);
    }

    public function test_cancelled_seeded_demo_subscription_uses_seeded_demo_reason(): void
    {
        $package = Package::query()->create([
            'code' => 'seeded-demo-plan',
            'name' => 'Seeded Demo Plan',
            'monthly_price' => 150000,
            'yearly_price' => 1500000,
            'billing_unit' => 'flat',
            'status' => 'active',
        ]);

        $company = Company::query()->create([
            'code' => 'SEEDCANCEL01',
            'name' => 'Seeded Cancel Demo Co',
            'legal_name' => null,
            'status' => 'active',
            'owner_user_id' => 1,
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        Subscription::query()->create([
            'company_id' => $company->id,
            'package_uuid' => $package->uuid,
            'plan_code' => $package->code,
            'status' => 'cancelled',
            'starts_at' => now()->subMonths(1),
            'ends_at' => now()->addMonths(10),
            'billing_cycle' => 'yearly',
            'amount' => 1500000,
            'termination_reason' => null,
            'metadata' => ['seed' => 'saas_ui_flow', 'company_code' => 'SEEDCANCEL01'],
        ]);

        $response = $this->adminRequest()
            ->getJson('/v1/saas/companies/billing-overview?tab=subscribed&per_page=50')
            ->assertOk()
            ->assertJsonPath('success', true);

        $rows = collect($response->json('data'));
        $row = $rows->firstWhere('company.code', 'SEEDCANCEL01');

        $this->assertNotNull($row);
        $this->assertSame('seeded_demo_state', $row['subscription']['cancellationReason'] ?? null);
        $this->assertStringContainsString('data demo seed', strtolower((string) ($row['subscription']['cancellationDescription'] ?? '')));
    }
}

