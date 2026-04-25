<?php

namespace Tests\Feature;

use App\Mail\InvoiceMailable;
use App\Mail\PaymentReminderMailable;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceMailRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_mailable_renders_without_mail_hint_errors(): void
    {
        $invoice = $this->createInvoice();

        $html = (new InvoiceMailable($invoice))->render();

        $this->assertStringContainsString((string) $invoice->invoice_number, $html);
        $this->assertStringContainsString('View Invoice', $html);
    }

    public function test_payment_reminder_mailable_renders_without_mail_hint_errors(): void
    {
        $invoice = $this->createInvoice();

        $html = (new PaymentReminderMailable($invoice))->render();

        $this->assertStringContainsString((string) $invoice->invoice_number, $html);
        $this->assertStringContainsString('Payment Reminder', $html);
    }

    private function createInvoice(): Invoice
    {
        $owner = User::query()->create([
            'name' => 'Mail Owner',
            'email' => 'mail-owner@example.com',
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
            'code' => 'MAIL01',
            'name' => 'Mail Co',
            'legal_name' => null,
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

        return Invoice::query()->create([
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'purchase_transaction_id' => null,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'amount_due' => 100000,
            'notes' => 'mail render test',
        ]);
    }
}