<?php

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Package;
use App\Models\PackageFeature;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

require __DIR__ . '/backend/vendor/autoload.php';

$app = require __DIR__ . '/backend/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

function log_line(string $message): void
{
    echo '[' . now()->format('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
}

function ensureTrialPackage(): Package
{
    $package = Package::query()->updateOrCreate(
        ['code' => 'trial'],
        [
            'name' => 'Trial (30 Hari)',
            'description' => 'Paket khusus trial untuk evaluasi. Otomatis aktif 30 hari lalu diminta upgrade.',
            'monthly_price' => 0,
            'yearly_price' => 0,
            'billing_unit' => 'company',
            'status' => 'active',
            'color' => '#6B7280',
            'sort_order' => 0,
        ]
    );

    $featureTemplate = [
        'employee_management' => 'Employee Management',
        'attendance' => 'Attendance',
        'leave_management' => 'Leave Management',
        'payroll' => 'Payroll',
        'performance' => 'Performance',
        'training' => 'Training',
        'goal_tracking' => 'Goal Tracking',
        'asset_management' => 'Asset Management',
        'api_access' => 'API Access',
        'priority_support' => 'Priority Support',
    ];

    $limits = [
        'employee_management' => 20,
        'attendance' => 1,
        'leave_management' => 1,
        'payroll' => 0,
        'performance' => 0,
        'training' => 0,
        'goal_tracking' => 0,
        'asset_management' => 0,
        'api_access' => 0,
        'priority_support' => 0,
    ];

    foreach ($featureTemplate as $featureCode => $featureName) {
        PackageFeature::query()->updateOrCreate(
            [
                'package_uuid' => $package->uuid,
                'feature_code' => $featureCode,
            ],
            [
                'feature_name' => $featureName,
                'limit' => $limits[$featureCode],
            ]
        );
    }

    return $package;
}

function ensureSuperAdmin(): User
{
    $email = strtolower(trim((string) config('hcm.admin_email', 'qa.login@example.com')));
    $password = (string) config('hcm.admin_password', 'StrongPass1');

    $user = User::query()->updateOrCreate(
        ['email' => $email],
        [
            'name' => 'Super User 1',
            'password' => Hash::make($password),
        ]
    );

    if (Schema::hasTable('companies') && Schema::hasTable('company_users')) {
        $defaultCompany = Company::query()->firstOrCreate(
            ['code' => 'default_company'],
            [
                'name' => 'Default Company',
                'legal_name' => 'Default Company',
                'status' => 'active',
                'owner_user_id' => $user->id,
                'timezone' => (string) config('app.timezone', 'UTC'),
                'currency' => 'IDR',
                'country_code' => 'ID',
            ]
        );

        CompanyUser::query()->firstOrCreate(
            [
                'company_id' => $defaultCompany->id,
                'user_id' => $user->id,
            ],
            [
                'role' => 'owner',
                'status' => 'active',
                'joined_at' => now(),
                'invited_by_user_id' => null,
            ]
        );
    }

    return $user;
}

function ensureTrialCompany(Package $package): Company
{
    $company = Company::query()->updateOrCreate(
        ['code' => 'e2e_trial_company'],
        [
            'name' => 'E2E Trial Company',
            'legal_name' => 'E2E Trial Company',
            'status' => 'active',
            'timezone' => (string) config('app.timezone', 'UTC'),
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]
    );

    if (Schema::hasTable('subscriptions')) {
        Subscription::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'package_uuid' => $package->uuid,
            ],
            [
                'plan_code' => $package->code,
                'status' => 'trial',
                'starts_at' => now(),
                'ends_at' => now()->addDays(30),
                'trial_ends_at' => now()->addDays(30),
                'auto_renew' => false,
                'billing_cycle' => 'monthly',
                'amount' => $package->monthly_price,
                'terminated_at' => null,
                'termination_reason' => null,
                'suspended_at' => null,
                'suspension_reason' => null,
                'metadata' => [
                    'seed_source' => 'test-e2e-scenario.php',
                    'scenario' => 'trial-package-super-admin-login',
                ],
            ]
        );
    }

    return $company;
}

log_line('Bootstrapping E2E scenario...');

$package = ensureTrialPackage();
log_line(sprintf('Trial package ready: %s (%s)', $package->name, $package->code));

$superAdmin = ensureSuperAdmin();
log_line(sprintf('Super admin ready: %s', $superAdmin->email));

if (! Auth::validate(['email' => $superAdmin->email, 'password' => (string) config('hcm.admin_password', 'StrongPass1')])) {
    throw new RuntimeException('Super admin credentials failed validation.');
}

log_line('Super admin credentials validated successfully.');

$company = ensureTrialCompany($package);
log_line(sprintf('Trial company ready: %s (%s)', $company->name, $company->code));

log_line('E2E scenario completed.');
log_line('Login with the super admin account below:');
log_line(sprintf('Email: %s', $superAdmin->email));
log_line(sprintf('Password: %s', (string) config('hcm.admin_password', 'StrongPass1')));
log_line(sprintf('Trial package code: %s', $package->code));
log_line(sprintf('Trial company code: %s', $company->code));
