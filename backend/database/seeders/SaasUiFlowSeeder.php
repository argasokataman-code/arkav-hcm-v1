<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\CustomDomain;
use App\Models\DashboardMetric;
use App\Models\Package;
use App\Models\PackageAddon;
use App\Models\PackageFeature;
use App\Models\PurchaseTransaction;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SaasUiFlowSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $qaUser = User::query()->updateOrCreate(
                ['email' => 'qa.login@example.com'],
                [
                    'name' => 'QA Login User',
                    'password' => Hash::make('StrongPass1'),
                ]
            );

            $packages = $this->seedPackages();
            $this->seedPackageAddons();
            $companies = $this->seedCompaniesAndMemberships($qaUser->id);
            $this->seedSubscriptions($companies, $packages);
            $this->seedPurchaseTransactions($companies);
            $this->seedCustomDomains($companies);
            $this->seedAuditLogs($qaUser->id, $companies);
            $this->seedDashboardMetrics($companies);
        });

        $this->command?->info('SaaS UI flow seed completed: 10 companies + packages + add-ons + subscriptions + domains + transactions + audit metrics.');
    }

    private function seedPackageAddons(): void
    {
        $addonRows = [
            [
                'code' => 'extra_users',
                'name' => 'Extra Users',
                'description' => 'Add more active users beyond the package limit.',
                'price_per_unit' => 25000,
                'unit_name' => 'user / month',
                'status' => 'active',
            ],
            [
                'code' => 'extra_companies',
                'name' => 'Extra Companies',
                'description' => 'Support for additional company entities in one tenant.',
                'price_per_unit' => 99000,
                'unit_name' => 'company / month',
                'status' => 'active',
            ],
            [
                'code' => 'api_calls',
                'name' => 'API Calls Booster',
                'description' => 'Extra API quota for integrations and automation.',
                'price_per_unit' => 150000,
                'unit_name' => '100k calls',
                'status' => 'active',
            ],
            [
                'code' => 'storage_gb',
                'name' => 'Storage Pack',
                'description' => 'Additional encrypted storage for documents and media.',
                'price_per_unit' => 12000,
                'unit_name' => 'GB',
                'status' => 'active',
            ],
            [
                'code' => 'premium_support',
                'name' => 'Premium Support',
                'description' => 'Priority support channel and faster response SLA.',
                'price_per_unit' => 399000,
                'unit_name' => 'month',
                'status' => 'inactive',
            ],
        ];

        foreach ($addonRows as $row) {
            PackageAddon::query()->updateOrCreate(
                ['code' => $row['code']],
                $row
            );
        }
    }

    /**
     * @return array<string, Package>
     */
    private function seedPackages(): array
    {
        $packageRows = [
            [
                'code' => 'starter',
                'name' => 'Starter',
                'description' => 'For small teams starting with HRMS.',
                'monthly_price' => 199000,
                'yearly_price' => 1990000,
                'billing_unit' => 'company',
                'status' => 'active',
                'color' => '#2D7FF9',
                'sort_order' => 10,
            ],
            [
                'code' => 'growth',
                'name' => 'Growth',
                'description' => 'For growing teams with payroll needs.',
                'monthly_price' => 399000,
                'yearly_price' => 3990000,
                'billing_unit' => 'company',
                'status' => 'active',
                'color' => '#00A76F',
                'sort_order' => 20,
            ],
            [
                'code' => 'business',
                'name' => 'Business',
                'description' => 'Advanced features for medium companies.',
                'monthly_price' => 699000,
                'yearly_price' => 6990000,
                'billing_unit' => 'company',
                'status' => 'active',
                'color' => '#FF9800',
                'sort_order' => 30,
            ],
            [
                'code' => 'enterprise',
                'name' => 'Enterprise',
                'description' => 'Enterprise workflow and compliance suite.',
                'monthly_price' => 1299000,
                'yearly_price' => 12990000,
                'billing_unit' => 'company',
                'status' => 'active',
                'color' => '#6C4CF1',
                'sort_order' => 40,
            ],
            [
                'code' => 'ultimate',
                'name' => 'Ultimate',
                'description' => 'Full feature access and priority support.',
                'monthly_price' => 1999000,
                'yearly_price' => 19990000,
                'billing_unit' => 'company',
                'status' => 'active',
                'color' => '#E53935',
                'sort_order' => 50,
            ],
        ];

        $featureTemplate = [
            'employee_management' => 'Employee Management',
            'attendance' => 'Attendance',
            'payroll' => 'Payroll',
            'leave_management' => 'Leave Management',
            'performance' => 'Performance',
            'asset_management' => 'Asset Management',
            'asset_logs' => 'Asset Logs',
            'asset_attachments' => 'Asset Attachments',
            'asset_warranty' => 'Asset Warranty',
            'asset_maintenance' => 'Asset Maintenance',
            'asset_reporting' => 'Asset Reporting',
            'asset_depreciation' => 'Asset Depreciation',
            'api_access' => 'API Access',
            'priority_support' => 'Priority Support',
        ];

        $featureLimitsByPackage = [
            'starter' => [50, 1, 0, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
            'growth' => [150, 1, 1, 1, 0, 1, 0, 1, 0, 0, 0, 0, 1, 0],
            'business' => [500, 1, 1, 1, 1, 1, 0, 1, 1, 1, 0, 0, 1, 0],
            'enterprise' => [null, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1],
            'ultimate' => [null, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1],
        ];

        $result = [];
        foreach ($packageRows as $row) {
            $package = Package::query()->updateOrCreate(
                ['code' => $row['code']],
                $row
            );
            $result[$row['code']] = $package;

            $i = 0;
            foreach ($featureTemplate as $featureCode => $featureName) {
                PackageFeature::query()->updateOrCreate(
                    [
                        'package_id' => $package->id,
                        'feature_code' => $featureCode,
                    ],
                    [
                        'feature_name' => $featureName,
                        'limit' => $featureLimitsByPackage[$row['code']][$i],
                    ]
                );
                $i++;
            }
        }

        return $result;
    }

    /**
     * @return array<int, Company>
     */
    private function seedCompaniesAndMemberships(int $qaUserId): array
    {
        $companyNames = [
            'Nusantara Labs',
            'Cakrawala Tech',
            'Bumi Retail Group',
            'Satelit Media',
            'Arunika Logistics',
            'Samudra Health',
            'Pilar Konstruksi',
            'Aurora Eduworks',
            'Lintas Prima Finance',
            'Vertex Manufacturing',
        ];

        $companies = [];
        foreach ($companyNames as $idx => $name) {
            $i = $idx + 1;
            $owner = User::query()->updateOrCreate(
                ['email' => sprintf('demo.owner%02d@example.com', $i)],
                [
                    'name' => sprintf('Demo Owner %02d', $i),
                    'password' => Hash::make('StrongPass1'),
                    'email_verified_at' => now(),
                ]
            );

            $company = Company::query()->updateOrCreate(
                ['code' => sprintf('demo_co_%02d', $i)],
                [
                    'name' => $name,
                    'legal_name' => $name.' PT',
                    'status' => $i % 5 === 0 ? 'inactive' : 'active',
                    'owner_user_id' => $owner->id,
                    'timezone' => 'Asia/Jakarta',
                    'currency' => 'IDR',
                    'country_code' => 'ID',
                ]
            );

            CompanyUser::query()->updateOrCreate(
                ['company_id' => $company->id, 'user_id' => $owner->id],
                [
                    'role' => 'owner',
                    'status' => 'active',
                    'joined_at' => now()->subMonths(6),
                    'invited_by_user_id' => null,
                ]
            );

            CompanyUser::query()->updateOrCreate(
                ['company_id' => $company->id, 'user_id' => $qaUserId],
                [
                    'role' => 'admin',
                    'status' => 'active',
                    'joined_at' => now()->subMonths(3),
                    'invited_by_user_id' => $owner->id,
                ]
            );

            $companies[] = $company;
        }

        return $companies;
    }

    /**
     * @param  array<int, Company>  $companies
     * @param  array<string, Package>  $packages
     */
    private function seedSubscriptions(array $companies, array $packages): void
    {
        $packageCodes = ['growth', 'starter', 'business', 'enterprise', 'ultimate'];
        $statuses = ['active', 'trial', 'active', 'paused', 'cancelled'];

        foreach ($companies as $idx => $company) {
            $packageCode = $packageCodes[$idx % count($packageCodes)];
            $package = $packages[$packageCode] ?? null;
            if (! $package) {
                $package = $packages[array_key_first($packages)];
            }
            $cycle = $idx % 2 === 0 ? 'monthly' : 'yearly';
            $status = $statuses[$idx % count($statuses)];
            $startsAt = Carbon::now()->subMonths(5 - ($idx % 5))->startOfDay();

            $trialEndsAt = $status === 'trial' ? (clone $startsAt)->addDays(14) : null;
            $endsAt = $cycle === 'yearly' ? (clone $startsAt)->addYear() : (clone $startsAt)->addMonth();

            $amount = $cycle === 'yearly'
                ? (float) $package->yearly_price
                : (float) $package->monthly_price;

            Subscription::query()->updateOrCreate(
                ['company_id' => $company->id],
                [
                    'package_id' => $package->id,
                    'plan_code' => $package->code,
                    'status' => $status,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'trial_ends_at' => $trialEndsAt,
                    'auto_renew' => $status !== 'cancelled',
                    'billing_cycle' => $cycle,
                    'amount' => $amount,
                    'metadata' => [
                        'seed' => 'saas_ui_flow',
                        'company_code' => $company->code,
                    ],
                ]
            );
        }
    }

    /**
     * @param  array<int, Company>  $companies
     */
    private function seedPurchaseTransactions(array $companies): void
    {
        foreach ($companies as $idx => $company) {
            $subscription = Subscription::query()->where('company_id', $company->id)->first();
            if (! $subscription) {
                continue;
            }

            $base = (float) $subscription->amount;
            $tax = round($base * 0.11, 2);
            $discount = $idx % 3 === 0 ? 50000 : 0;
            $total = max(0, $base + $tax - $discount);

            PurchaseTransaction::query()->updateOrCreate(
                ['transaction_code' => sprintf('DEMO-TXN-%03d-A', $idx + 1)],
                [
                    'company_id' => $company->id,
                    'subscription_id' => $subscription->id,
                    'transaction_type' => 'subscription',
                    'description' => 'Initial subscription invoice',
                    'amount' => $base,
                    'tax_amount' => $tax,
                    'discount_amount' => $discount,
                    'total_amount' => $total,
                    'billing_period_start' => Carbon::now()->startOfMonth(),
                    'billing_period_end' => Carbon::now()->endOfMonth(),
                    'due_date' => Carbon::now()->addDays(7),
                    'paid_at' => $idx % 4 === 0 ? null : Carbon::now()->subDays($idx + 1),
                    'payment_method' => $idx % 2 === 0 ? 'bank_transfer' : 'credit_card',
                    'payment_reference' => sprintf('PAYREF-%03d-A', $idx + 1),
                    'status' => $idx % 4 === 0 ? 'issued' : 'paid',
                    'notes' => 'Seeded demo transaction for UI flow testing.',
                ]
            );

            PurchaseTransaction::query()->updateOrCreate(
                ['transaction_code' => sprintf('DEMO-TXN-%03d-B', $idx + 1)],
                [
                    'company_id' => $company->id,
                    'subscription_id' => $subscription->id,
                    'transaction_type' => 'addon',
                    'description' => 'Additional seat purchase',
                    'amount' => 150000,
                    'tax_amount' => 16500,
                    'discount_amount' => 0,
                    'total_amount' => 166500,
                    'billing_period_start' => Carbon::now()->startOfMonth(),
                    'billing_period_end' => Carbon::now()->endOfMonth(),
                    'due_date' => Carbon::now()->addDays(10),
                    'paid_at' => null,
                    'payment_method' => 'e_wallet',
                    'payment_reference' => sprintf('PAYREF-%03d-B', $idx + 1),
                    'status' => $idx % 2 === 0 ? 'sent' : 'overdue',
                    'notes' => 'Seeded addon transaction for UI flow testing.',
                ]
            );
        }
    }

    /**
     * @param  array<int, Company>  $companies
     */
    private function seedCustomDomains(array $companies): void
    {
        $statuses = ['verified', 'pending', 'failed', 'verified', 'inactive'];

        foreach ($companies as $idx => $company) {
            $status = $statuses[$idx % count($statuses)];
            $domain = sprintf('demo%02d.arcav.local', $idx + 1);
            $verifiedAt = $status === 'verified' ? Carbon::now()->subDays($idx + 2) : null;
            $failedAt = $status === 'failed' ? Carbon::now()->subDays(1) : null;

            CustomDomain::query()->updateOrCreate(
                ['domain' => $domain],
                [
                    'company_id' => $company->id,
                    'status' => $status,
                    'verification_token' => sprintf('verify_demo_%02d_token', $idx + 1),
                    'verified_at' => $verifiedAt,
                    'verification_failed_at' => $failedAt,
                    'verification_method' => 'dns',
                    'verification_record' => sprintf('v=arcav verify_demo_%02d', $idx + 1),
                    'verification_response' => $status === 'failed' ? 'DNS TXT mismatch' : 'OK',
                    'verification_attempts' => $status === 'pending' ? 1 : 2,
                    'last_verification_attempt_at' => Carbon::now()->subHours(6),
                    'active_from' => $status === 'verified' ? Carbon::now()->subDays(7) : null,
                    'active_until' => null,
                    'notes' => 'Seeded domain for SaaS demo flow.',
                ]
            );
        }
    }

    /**
     * @param  array<int, Company>  $companies
     */
    private function seedAuditLogs(int $superAdminId, array $companies): void
    {
        AuditLog::query()->where('user_agent', 'seed:saas-ui-flow')->delete();

        $actions = [
            'view_dashboard',
            'modify_subscription',
            'delete_company',
            'refund_transaction',
            'modify_billing',
            'view_audit_logs',
            'export_report',
        ];

        foreach ($companies as $idx => $company) {
            AuditLog::query()->create([
                'super_admin_id' => $superAdminId,
                'action' => $actions[$idx % count($actions)],
                'target_type' => 'company',
                'target_id' => $company->id,
                'details' => [
                    'seed' => 'saas_ui_flow',
                    'company_code' => $company->code,
                    'note' => 'Generated for dashboard audit log testing.',
                ],
                'ip_address' => '127.0.0.1',
                'user_agent' => 'seed:saas-ui-flow',
                'created_at' => Carbon::now()->subDays($idx),
                'updated_at' => Carbon::now()->subDays($idx),
            ]);
        }
    }

    /**
     * @param  array<int, Company>  $companies
     */
    private function seedDashboardMetrics(array $companies): void
    {
        $metricKeys = ['mrr', 'arr', 'total_companies', 'active_subscriptions', 'total_users'];

        for ($m = 0; $m < 6; $m++) {
            $date = Carbon::now()->subMonths(5 - $m)->startOfMonth()->toDateString();

            $values = [
                'mrr' => 5000000 + ($m * 1250000),
                'arr' => (5000000 + ($m * 1250000)) * 12,
                'total_companies' => count($companies),
                'active_subscriptions' => 6 + $m,
                'total_users' => 20 + ($m * 3),
            ];

            foreach ($metricKeys as $key) {
                DashboardMetric::query()->updateOrCreate(
                    [
                        'metric_date' => $date,
                        'metric_key' => $key,
                    ],
                    [
                        'metric_value' => $values[$key],
                        'metric_metadata' => ['seed' => 'saas_ui_flow'],
                        'calculated_at' => Carbon::now()->subHours(2),
                        'next_calculation_at' => Carbon::now()->addHours(2),
                    ]
                );
            }
        }
    }
}
