<?php
/**
 * E2E Test Scenario Setup
 * 
 * Flow:
 * 1. Create company with trial subscription
 * 2. Create user account (owner)
 * 3. Create employee record linked to user
 * 4. Test login in both modes (Regular + Company Mode)
 * 5. Create admin role
 * 6. Assign admin role to employee
 */

// Bootstrap Laravel
require __DIR__ . '/backend/bootstrap/app.php';

use Illuminate\Support\Facades\Artisan;

$app = require __DIR__ . '/backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\EmployeeProfile;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\HcmRole;
use Illuminate\Support\Facades\Hash;

echo "=== E2E Test Scenario Setup ===\n\n";

// Step 1: Create test user
echo "Step 1: Creating test user...\n";
$testUser = User::firstOrCreate(
    ['email' => 'e2e-test@arcav.test'],
    [
        'name' => 'E2E Test User',
        'password' => Hash::make('password123'),
        'email_verified_at' => now(),
    ]
);
echo "✓ User created: {$testUser->email} (ID: {$testUser->id})\n\n";

// Step 2: Create test company
echo "Step 2: Creating test company...\n";
$testCompany = Company::firstOrCreate(
    ['code' => 'e2e_test_company'],
    [
        'name' => 'E2E Test Company',
        'description' => 'Company for E2E testing',
        'status' => 'active',
    ]
);
echo "✓ Company created: {$testCompany->name} (code: {$testCompany->code}, ID: {$testCompany->id})\n\n";

// Step 3: Add user to company as owner
echo "Step 3: Adding user to company as owner...\n";
$companyUser = CompanyUser::firstOrCreate(
    [
        'company_id' => $testCompany->id,
        'user_id' => $testUser->id,
    ],
    [
        'role' => 'owner',
        'status' => 'active',
        'joined_at' => now(),
    ]
);
echo "✓ User added to company as {$companyUser->role}\n\n";

// Step 4: Create or get trial package
echo "Step 4: Setting up trial subscription...\n";
$trialPackage = Package::where('code', 'trial')->orWhere('name', 'like', '%trial%')->first();
if (!$trialPackage) {
    echo "⚠ Trial package not found, skipping subscription\n";
} else {
    $subscription = Subscription::firstOrCreate(
        [
            'company_id' => $testCompany->id,
            'package_id' => $trialPackage->id,
        ],
        [
            'status' => 'active',
            'start_date' => now(),
            'end_date' => now()->addDays(14),
        ]
    );
    echo "✓ Subscription created: {$trialPackage->name} (ID: {$subscription->id})\n";
}
echo "\n";

// Step 5: Create employee profile
echo "Step 5: Creating employee profile...\n";
$employee = EmployeeProfile::firstOrCreate(
    [
        'company_id' => $testCompany->id,
        'user_id' => $testUser->id,
    ],
    [
        'name' => $testUser->name,
        'email' => $testUser->email,
        'status' => 'active',
        'employment_date' => now(),
    ]
);
echo "✓ Employee profile created (ID: {$employee->id})\n\n";

// Step 6: Create admin role
echo "Step 6: Creating admin role...\n";
$adminRole = HcmRole::firstOrCreate(
    [
        'company_id' => $testCompany->id,
        'code' => 'ADMIN_E2E',
    ],
    [
        'name' => 'Admin (E2E Test)',
        'description' => 'Test admin role for dashboard control',
        'status' => 'active',
        'is_system' => false,
    ]
);
echo "✓ Admin role created (ID: {$adminRole->id})\n\n";

// Summary
echo "=== Test Setup Complete ===\n";
echo "Test User Email: {$testUser->email}\n";
echo "Test Password: password123\n";
echo "Company Code: {$testCompany->code}\n";
echo "Company Name: {$testCompany->name}\n";
echo "Employee ID: {$employee->id}\n";
echo "Admin Role: {$adminRole->code}\n";
echo "\n";

echo "Next steps:\n";
echo "1. Go to login page\n";
echo "2. Login in Regular Mode with: {$testUser->email} / password123\n";
echo "3. Verify employee dashboard access\n";
echo "4. Logout\n";
echo "5. Login in Company Mode with company code: {$testCompany->code}\n";
echo "6. Verify admin dashboard + user/roles management\n";
echo "7. Create/assign admin role to employee\n";

echo "\n✓ Test scenario ready!\n";
?>
