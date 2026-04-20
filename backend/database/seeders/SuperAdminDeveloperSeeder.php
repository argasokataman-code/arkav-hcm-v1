<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\HcmManualActivity;
use App\Models\HcmUserRole;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminDeveloperSeeder extends Seeder
{
    /**
     * Run the seeder.
     */
    public function run(): void
    {
        $this->command->info('Setting up Super Admin Developer account...');

        // 1. Create super admin user (locked to specific email)
        $superAdminEmail = 'qa.login@example.com';
        $superAdmin = User::firstOrCreate(
            ['email' => $superAdminEmail],
            [
                'name' => 'Super Admin Developer',
                'password' => Hash::make('StrongPass1'),
                'email_verified_at' => now(),
            ]
        );
        $this->command->info("✓ Super admin user created: {$superAdmin->email}");

        // 2. Global HCM admin is identified by email match with config
        // Email 'qa.login@example.com' matches config('hcm.admin_email') → automatic global admin
        // No need for HcmUserRole assignment
        $this->command->info('✓ Global admin access enabled via email: qa.login@example.com');

        // 3. Seed test companies with activities
        $packages = Package::where('status', 'active')->get();
        if ($packages->isEmpty()) {
            $this->command->warn('No packages found. Seeding landing packages first...');
            $this->call(LandingPackagesSeeder::class);
            $packages = Package::where('status', 'active')->get();
        }

        $testData = [
            [
                'name' => 'Test Company Alpha',
                'code_base' => 'test-alpha',
                'package_code' => 'starter',
                'activities' => [
                    'Company Setup Completed',
                    'Employees Added: 5',
                    'Payroll Configuration Done',
                    'Leave Policy Created',
                ],
            ],
            [
                'name' => 'Test Company Beta',
                'code_base' => 'test-beta',
                'package_code' => 'growth',
                'activities' => [
                    'Company Registered',
                    'Admin User Assigned',
                    'Department Structure Created',
                    'Attendance System Configured',
                ],
            ],
            [
                'name' => 'Test Company Gamma',
                'code_base' => 'test-gamma',
                'package_code' => 'business',
                'activities' => [
                    'Onboarding Complete',
                    'Payroll Run: January 2026',
                    'Invoice Paid: Invoice #2026-001',
                    'Subscription Activated',
                ],
            ],
        ];

        foreach ($testData as $data) {
            $companyCode = $data['code_base'] . '_' . substr(bin2hex(random_bytes(2)), 0, 4);
            
            $company = Company::firstOrCreate(
                ['code' => $companyCode],
                [
                    'name' => $data['name'],
                    'owner_user_id' => $superAdmin->id,
                    'status' => 'active',
                    'timezone' => 'Asia/Jakarta',
                    'currency' => 'IDR',
                    'country_code' => 'ID',
                ]
            );

            $this->command->info("✓ Test company created: {$company->name} ({$company->code})");

            // Add super admin as owner to each test company
            CompanyUser::firstOrCreate(
                ['user_id' => $superAdmin->id, 'company_id' => $company->id],
                [
                    'role' => 'owner',
                    'status' => 'active',
                    'joined_at' => now(),
                ]
            );

            // Create subscription
            $package = $packages->firstWhere('code', $data['package_code']) ?? $packages->first();
            $subscription = Subscription::firstOrCreate(
                ['company_id' => $company->id],
                [
                    'package_uuid' => $package->uuid,
                    'plan_code' => $package->code,
                    'status' => 'active',
                    'starts_at' => now()->subDays(30),
                    'ends_at' => now()->addDays(60),
                    'trial_ends_at' => null,
                    'auto_renew' => true,
                    'billing_cycle' => 'monthly',
                    'amount' => $package->monthly_price,
                ]
            );

            // Create paid invoice
            $invoice = Invoice::firstOrCreate(
                ['subscription_id' => $subscription->id],
                [
                    'company_id' => $company->id,
                    'issue_date' => now()->subDays(15)->toDateString(),
                    'due_date' => now()->addDays(15)->toDateString(),
                    'amount_due' => $package->monthly_price,
                    'status' => 'paid',
                    'is_paid' => true,
                    'paid_date' => now()->subDays(10),
                    'notes' => 'Test invoice - QA seeding',
                ]
            );
            $this->command->info("  → Subscription & Invoice created");

            // Create activities for each test company
            foreach ($data['activities'] as $activityTitle) {
                HcmManualActivity::create([
                    'company_id' => $company->id,
                    'created_by_user_id' => $superAdmin->id,
                    'title' => $activityTitle,
                    'activity_kind' => 'note',
                    'status' => 'completed',
                    'due_date' => now()->addDays(rand(1, 30)),
                ]);
            }
            $this->command->info('  → '.count($data['activities']).' activities seeded');
        }

        $this->command->info('');
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->info('✓ Super Admin Developer Account Setup Complete!');
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->info('Email: qa.login@example.com');
        $this->command->info('Password: StrongPass1');
        $this->command->info('Role: Global HCM Admin (unrestricted access)');
        $this->command->info('Test Companies: 3 (with activities, subscriptions, invoices)');
        $this->command->info('');
        $this->command->info('Features Available:');
        $this->command->info('  • View activities from all test companies');
        $this->command->info('  • Access via: /activity?companyId=X');
        $this->command->info('  • List all companies: /v1/hcm/activity-feed-companies');
        $this->command->info('═══════════════════════════════════════════════════════');
    }
}
