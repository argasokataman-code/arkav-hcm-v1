<?php

namespace Tests\Feature;

use App\Jobs\SendPaymentReminder;
use App\Mail\PaymentReminderMailable;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Invoice;
use App\Models\InvoiceEmailLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendPaymentReminderJobTest extends TestCase
{
    use RefreshDatabase;

    private int $queryCount = 0;

    private function startQueryTracking(): void
    {
        $this->queryCount = 0;
        DB::listen(function ($query): void {
            ++$this->queryCount;
        });
    }

    private function assertQueryCountLessThan(int $max, string $label = 'dispatch'): void
    {
        $this->assertLessThanOrEqual(
            $max,
            $this->queryCount,
            "Query count exceeded limit for {$label}. Expected ≤{$max}, got {$this->queryCount}. Consider adding ->select(...) to queries in this code path."
        );
    }

    public function test_job_sends_reminder_to_owner_email_and_logs_canonical_event_key(): void
    {
        Mail::fake();

        $owner = User::query()->create([
            'name' => 'Owner Billing',
            'email' => 'owner.billing@example.com',
            'password' => bcrypt('StrongPass1'),
        ]);

        $company = Company::query()->create([
            'code' => 'REM001',
            'name' => 'Reminder Co',
            'legal_name' => null,
            'status' => 'active',
            'owner_user_id' => $owner->id,
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $invoice = Invoice::query()->create([
            'company_id' => $company->id,
            'subscription_id' => null,
            'purchase_transaction_id' => null,
            'issue_date' => now()->subDays(10)->toDateString(),
            'due_date' => now()->subDay()->toDateString(),
            'amount_due' => 150000,
            'is_paid' => false,
            'status' => 'issued',
            'notes' => null,
        ]);

        $this->startQueryTracking();
        (new SendPaymentReminder)->handle();
        $this->assertQueryCountLessThan(10, 'payment_reminder_owner');

        Mail::assertSent(PaymentReminderMailable::class);

        $this->assertDatabaseHas('invoice_email_logs', [
            'invoice_id' => $invoice->id,
            'to_email' => 'owner.billing@example.com',
            'event_key' => 'billing.invoice.reminder_sent',
            'status' => 'sent',
        ]);

        $this->assertSame(1, InvoiceEmailLog::query()->where('invoice_id', $invoice->id)->count());

        $this->assertDatabaseHas('notification_deliveries', [
            'event_key' => 'billing.invoice.reminder_sent',
            'channel' => 'mail',
            'status' => 'sent',
            'recipient' => 'owner.billing@example.com',
        ]);
    }

    public function test_job_uses_active_company_member_fallback_when_owner_email_missing(): void
    {
        Mail::fake();

        $fallbackAdmin = User::query()->create([
            'name' => 'Fallback Admin',
            'email' => 'fallback.admin@example.com',
            'password' => bcrypt('StrongPass1'),
        ]);

        $company = Company::query()->create([
            'code' => 'REM002',
            'name' => 'Reminder Fallback Co',
            'legal_name' => null,
            'status' => 'active',
            'owner_user_id' => null,
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $fallbackAdmin->id,
            'role' => 'admin',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $invoice = Invoice::query()->create([
            'company_id' => $company->id,
            'subscription_id' => null,
            'purchase_transaction_id' => null,
            'issue_date' => now()->subDays(8)->toDateString(),
            'due_date' => now()->subDay()->toDateString(),
            'amount_due' => 125000,
            'is_paid' => false,
            'status' => 'issued',
            'notes' => null,
        ]);

        $this->startQueryTracking();
        (new SendPaymentReminder)->handle();
        $this->assertQueryCountLessThan(10, 'payment_reminder_fallback');

        Mail::assertSent(PaymentReminderMailable::class, 1);
        Mail::assertSent(PaymentReminderMailable::class, function (PaymentReminderMailable $mail) use ($invoice): bool {
            return (int) $mail->invoice->id === (int) $invoice->id;
        });

        $this->assertDatabaseHas('invoice_email_logs', [
            'invoice_id' => $invoice->id,
            'to_email' => 'fallback.admin@example.com',
            'event_key' => 'billing.invoice.reminder_sent',
            'status' => 'sent',
        ]);

        $this->assertDatabaseHas('notification_deliveries', [
            'event_key' => 'billing.invoice.reminder_sent',
            'channel' => 'mail',
            'status' => 'sent',
            'recipient' => 'fallback.admin@example.com',
        ]);
    }
}
