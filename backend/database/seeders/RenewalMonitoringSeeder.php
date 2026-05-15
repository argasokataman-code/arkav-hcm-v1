<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\PurchaseTransaction;
use App\Models\Subscription;
use App\Models\SubscriptionEvent;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * RenewalMonitoringSeeder
 *
 * Creates 5 demo companies covering every Renewal Monitoring scenario:
 *   A) Paid renewal        — invoice settled, subscription active
 *   B) Retrying            — payment failed once, retry scheduled
 *   C) Grace Period        — max retries exceeded, grace period active
 *   D) Suspended           — grace period expired, account locked
 *      -> login triggers the "Akun dinonaktifkan" popup modal
 *   E) Anomaly             — Xendit gateway down during renewal cycle
 *
 * Login credentials (password: StrongPass1 for all):
 *   renewal.paid@example.com       — Scenario A
 *   renewal.retry@example.com      — Scenario B
 *   renewal.grace@example.com      — Scenario C
 *   renewal.suspended@example.com  — Scenario D  (triggers popup)
 *   renewal.anomaly@example.com    — Scenario E
 */
class RenewalMonitoringSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $package = Package::query()
                ->where('status', 'active')
                ->orderBy('sort_order')
                ->first();

            if (! $package) {
                $this->command?->warn('No active package found — run LandingPackagesSeeder first.');
                return;
            }

            $periodKey = sprintf('sub_%%d_%s', now()->format('Y_m'));

            // Scenario A: Paid renewal
            $userA = $this->upsertUser('renewal.paid@example.com', 'Renewal Demo - Paid');
            $companyA = $this->upsertCompany('RNWL-A', 'Renewal Paid Co');
            $this->upsertMembership($userA, $companyA);
            $subA = $this->upsertSubscription($companyA, $package, [
                'status'    => 'active',
                'starts_at' => now()->subMonth(),
                'ends_at'   => now()->addMonth(),
            ]);
            $txA = $this->upsertTransaction($companyA, $package, 'TRX-RNWL-A-' . now()->format('Ymd'));
            Invoice::updateOrCreate(
                ['invoice_number' => 'INV-RNWL-A-' . now()->format('Ym')],
                [
                    'company_id'              => $companyA->id,
                    'subscription_id'         => $subA->id,
                    'purchase_transaction_id' => $txA->id,
                    'renewal_period_key'      => sprintf($periodKey, $subA->id),
                    'renewal_reason_code'     => 'RENEWAL_INVOICE_CREATED',
                    'renewal_reason_message'  => 'Invoice created and settled on renewal cycle.',
                    'issue_date'              => now()->subDays(5),
                    'due_date'                => now()->subDays(1),
                    'amount_due'              => $package->monthly_price ?? 199000,
                    'is_paid'                 => true,
                    'paid_date'               => now()->subDays(3),
                    'status'                  => 'paid',
                ]
            );

            // Scenario B: Retrying
            $userB = $this->upsertUser('renewal.retry@example.com', 'Renewal Demo - Retry');
            $companyB = $this->upsertCompany('RNWL-B', 'Renewal Retry Co');
            $this->upsertMembership($userB, $companyB);
            $subB = $this->upsertSubscription($companyB, $package, [
                'status'    => 'active',
                'starts_at' => now()->subMonth(),
                'ends_at'   => now()->addDays(3),
            ]);
            $txB = $this->upsertTransaction($companyB, $package, 'TRX-RNWL-B-' . now()->format('Ymd'));
            Invoice::updateOrCreate(
                ['invoice_number' => 'INV-RNWL-B-' . now()->format('Ym')],
                [
                    'company_id'              => $companyB->id,
                    'subscription_id'         => $subB->id,
                    'purchase_transaction_id' => $txB->id,
                    'renewal_period_key'      => sprintf($periodKey, $subB->id),
                    'renewal_reason_code'     => 'RENEWAL_RETRY_SCHEDULED',
                    'renewal_reason_message'  => 'Payment gateway failure — retry scheduled at next window.',
                    'issue_date'              => now()->subDays(2),
                    'due_date'                => now()->addDay(),
                    'amount_due'              => $package->monthly_price ?? 199000,
                    'is_paid'                 => false,
                    'status'                  => 'sent',
                ]
            );

            // Scenario C: Grace Period
            $userC = $this->upsertUser('renewal.grace@example.com', 'Renewal Demo - Grace');
            $companyC = $this->upsertCompany('RNWL-C', 'Renewal Grace Co');
            $this->upsertMembership($userC, $companyC);
            $subC = $this->upsertSubscription($companyC, $package, [
                'status'           => 'grace_period',
                'starts_at'        => now()->subMonths(2),
                'ends_at'          => now()->subDays(5),
                'grace_started_at' => now()->subDays(5),
                'grace_ends_at'    => now()->addDays(9),
            ]);
            $txC = $this->upsertTransaction($companyC, $package, 'TRX-RNWL-C-' . now()->format('Ymd'));
            Invoice::updateOrCreate(
                ['invoice_number' => 'INV-RNWL-C-' . now()->format('Ym')],
                [
                    'company_id'              => $companyC->id,
                    'subscription_id'         => $subC->id,
                    'purchase_transaction_id' => $txC->id,
                    'renewal_period_key'      => sprintf($periodKey, $subC->id),
                    'renewal_reason_code'     => 'RENEWAL_MAX_RETRY_EXCEEDED',
                    'renewal_reason_message'  => 'Maximum retry attempts reached. Subscription moved to grace period.',
                    'issue_date'              => now()->subDays(7),
                    'due_date'                => now()->subDays(5),
                    'amount_due'              => $package->monthly_price ?? 199000,
                    'is_paid'                 => false,
                    'status'                  => 'expired',
                ]
            );
            SubscriptionEvent::updateOrCreate(
                ['renewal_period_key' => sprintf($periodKey, $subC->id), 'reason_code' => 'RENEWAL_GRACE_PERIOD_STARTED'],
                [
                    'company_id'        => $companyC->id,
                    'company_uuid'      => $companyC->uuid,
                    'subscription_id'   => $subC->id,
                    'subscription_uuid' => $subC->uuid,
                    'event_type'        => 'subscription.grace_period_started',
                    'reason_code'       => 'RENEWAL_GRACE_PERIOD_STARTED',
                    'reason_message'    => 'Grace period started after max retry exceeded.',
                    'occurred_at'       => now()->subDays(5),
                ]
            );

            // Scenario D: INACTIVE — renewal grace expired, account access locked
            $userD = $this->upsertUser('renewal.suspended@example.com', 'Renewal Demo - Suspended');
            $companyD = $this->upsertCompany('RNWL-D', 'Renewal Suspended Co');
            $this->upsertMembership($userD, $companyD);
            $subD = $this->upsertSubscription($companyD, $package, [
                'status'            => 'inactive',
                'starts_at'         => now()->subMonths(2),
                'ends_at'           => now()->subDays(20),
                'auto_renew'        => false,
                'grace_started_at'  => now()->subDays(20),
                'grace_ends_at'     => now()->subDays(6),
                'suspended_at'      => now()->subDays(6),
                'suspension_reason' => 'Grace period expired without payment. Account inactive pending renewal handling.',
            ]);
            $companyD->forceFill(['status' => 'inactive'])->save();
            $txD = $this->upsertTransaction($companyD, $package, 'TRX-RNWL-D-' . now()->format('Ymd'));
            Invoice::updateOrCreate(
                ['invoice_number' => 'INV-RNWL-D-' . now()->format('Ym')],
                [
                    'company_id'              => $companyD->id,
                    'subscription_id'         => $subD->id,
                    'purchase_transaction_id' => $txD->id,
                    'renewal_period_key'      => sprintf($periodKey, $subD->id),
                    'renewal_reason_code'     => 'RENEWAL_GRACE_EXPIRED',
                    'renewal_reason_message'  => 'Grace period expired. Subscription suspended.',
                    'issue_date'              => now()->subDays(22),
                    'due_date'                => now()->subDays(20),
                    'amount_due'              => $package->monthly_price ?? 199000,
                    'is_paid'                 => false,
                    'status'                  => 'expired',
                    'notes'                   => 'Account suspended. Contact super admin to reactivate.',
                ]
            );
            SubscriptionEvent::updateOrCreate(
                ['renewal_period_key' => sprintf($periodKey, $subD->id), 'reason_code' => 'RENEWAL_GRACE_EXPIRED'],
                [
                    'company_id'        => $companyD->id,
                    'company_uuid'      => $companyD->uuid,
                    'subscription_id'   => $subD->id,
                    'subscription_uuid' => $subD->uuid,
                    'event_type'        => 'subscription.inactive',
                    'reason_code'       => 'RENEWAL_GRACE_EXPIRED',
                    'reason_message'    => 'Account inactive: grace period expired without payment.',
                    'occurred_at'       => now()->subDays(6),
                ]
            );

            // Scenario E: Anomaly (XENDIT_DOWN)
            $userE = $this->upsertUser('renewal.anomaly@example.com', 'Renewal Demo - Anomaly');
            $companyE = $this->upsertCompany('RNWL-E', 'Renewal Anomaly Co');
            $this->upsertMembership($userE, $companyE);
            $subE = $this->upsertSubscription($companyE, $package, [
                'status'    => 'active',
                'starts_at' => now()->subMonth(),
                'ends_at'   => now()->addDays(5),
            ]);
            $txE = $this->upsertTransaction($companyE, $package, 'TRX-RNWL-E-' . now()->format('Ymd'));
            Invoice::updateOrCreate(
                ['invoice_number' => 'INV-RNWL-E-' . now()->format('Ym')],
                [
                    'company_id'              => $companyE->id,
                    'subscription_id'         => $subE->id,
                    'purchase_transaction_id' => $txE->id,
                    'renewal_period_key'      => sprintf($periodKey, $subE->id),
                    'renewal_reason_code'     => 'XENDIT_DOWN',
                    'renewal_reason_message'  => 'Xendit payment gateway unavailable during renewal processing. Manual intervention required.',
                    'issue_date'              => now()->subDays(1),
                    'due_date'                => now()->addDays(2),
                    'amount_due'              => $package->monthly_price ?? 199000,
                    'is_paid'                 => false,
                    'status'                  => 'sent',
                    'notes'                   => 'ANOMALY: gateway downtime detected. Requires ops team follow-up.',
                ]
            );
        });

        $this->command?->info(implode(PHP_EOL, [
            'RenewalMonitoringSeeder complete — 5 scenarios created:',
            '  A) renewal.paid@example.com      — Paid renewal (active)',
            '  B) renewal.retry@example.com     — Retrying (payment failed)',
            '  C) renewal.grace@example.com     — Grace period active',
            '  D) renewal.suspended@example.com — SUSPENDED -> popup on login',
            '  E) renewal.anomaly@example.com   — Anomaly (XENDIT_DOWN)',
            '  Password for all: StrongPass1',
        ]));
    }

    private function upsertUser(string $email, string $name): User
    {
        return User::updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Hash::make('StrongPass1')]
        );
    }

    private function upsertCompany(string $code, string $name): Company
    {
        return Company::updateOrCreate(
            ['code' => $code],
            ['name' => $name]
        );
    }

    private function upsertMembership(User $user, Company $company): void
    {
        CompanyUser::updateOrCreate(
            ['user_id' => $user->id, 'company_id' => $company->id],
            ['role' => 'owner']
        );
    }

    private function upsertSubscription(Company $company, Package $package, array $overrides): Subscription
    {
        return Subscription::updateOrCreate(
            ['company_id' => $company->id],
            array_merge([
                'package_uuid'  => $package->uuid,
                'plan_code'     => $package->code,
                'billing_cycle' => 'monthly',
                'amount'        => $package->monthly_price ?? 199000,
                'auto_renew'    => true,
            ], $overrides)
        );
    }

    private function upsertTransaction(Company $company, Package $package, string $code): PurchaseTransaction
    {
        return PurchaseTransaction::updateOrCreate(
            ['transaction_code' => $code],
            [
                'company_id'       => $company->id,
                'transaction_type' => 'subscription',
                'description'      => 'Renewal — ' . $package->name . ' (monthly)',
                'amount'           => $package->monthly_price ?? 199000,
                'total_amount'     => $package->monthly_price ?? 199000,
                'status'           => 'issued',
            ]
        );
    }
}
