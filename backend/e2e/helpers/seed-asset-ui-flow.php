<?php

declare(strict_types=1);

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\EmployeeProfile;
use App\Models\Package;
use App\Models\PackageFeature;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$runId = getenv('PW_ASSET_UI_RUN_ID') ?: strtolower(substr((string) microtime(true), -8));
$ownerPassword = getenv('PW_ASSET_UI_PASSWORD') ?: 'StrongPass1';
$companyCode = 'assetui_'.$runId;
$ownerEmail = 'asset.ui.'.$runId.'@example.com';

$company = Company::query()->firstOrCreate(
    ['code' => $companyCode],
    [
        'name' => 'Asset UI '.$runId,
        'legal_name' => 'Asset UI '.$runId.' Ltd',
        'status' => 'active',
        'timezone' => 'UTC',
        'currency' => 'IDR',
        'country_code' => 'ID',
    ]
);

$package = Package::query()->firstOrCreate(
    ['code' => 'asset-ui-'.$runId],
    [
        'name' => 'Asset UI Package '.$runId,
        'monthly_price' => 199000,
        'yearly_price' => 1990000,
        'billing_unit' => 'company',
        'status' => 'active',
    ]
);

foreach ([
    ['asset_management', 'Asset Management'],
    ['asset_attachments', 'Asset Attachments'],
    ['tickets', 'Tickets'],
] as [$featureCode, $featureName]) {
    PackageFeature::query()->firstOrCreate(
        [
            'package_uuid' => $package->uuid,
            'feature_code' => $featureCode,
        ],
        [
            'feature_name' => $featureName,
            'limit' => null,
        ]
    );
}

Subscription::query()->updateOrCreate(
    [
        'company_id' => $company->id,
        'package_uuid' => $package->uuid,
        'plan_code' => $package->code,
    ],
    [
        'status' => 'active',
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addMonth(),
        'billing_cycle' => 'monthly',
        'amount' => 199000,
    ]
);

$owner = User::query()->updateOrCreate(
    ['email' => $ownerEmail],
    [
        'name' => 'Asset UI Owner '.$runId,
        'password' => Hash::make($ownerPassword),
    ]
);

CompanyUser::query()->updateOrCreate(
    [
        'company_id' => $company->id,
        'user_id' => $owner->id,
    ],
    [
        'role' => 'admin',
        'status' => 'active',
        'joined_at' => now()->subDay(),
        'invited_by_user_id' => null,
    ]
);

EmployeeProfile::query()->updateOrCreate(
    [
        'company_id' => $company->id,
        'user_id' => $owner->id,
    ],
    [
        'employment_status' => 'active',
        'designation' => 'Admin',
        'team' => 'HCM',
        'nik' => 'AST-UI-'.strtoupper($runId),
        'hire_date' => now()->subMonth()->toDateString(),
    ]
);

$category = AssetCategory::query()->firstOrCreate(
    [
        'company_id' => $company->id,
        'code' => strtoupper('ui_'.$runId),
    ],
    [
        'name' => 'UI Devices '.$runId,
        'description' => 'Assets seeded for browser issue-report and attachment flow.',
        'is_active' => true,
    ]
);

$asset = Asset::query()->firstOrCreate(
    [
        'company_id' => $company->id,
        'asset_code' => 'AST-UI-'.strtoupper($runId),
    ],
    [
        'asset_category_id' => $category->id,
        'name' => 'UI Asset '.$runId,
        'brand' => 'Arcav',
        'model' => 'Browser Flow',
        'serial_number' => 'SN-'.$runId,
        'purchase_date' => now()->subMonth()->toDateString(),
        'purchase_price' => 2500000,
        'condition' => 'good',
        'status' => 'available',
        'location' => 'Jakarta Office',
        'notes' => 'Seeded for Playwright asset flow.',
    ]
);

echo json_encode([
    'runId' => $runId,
    'companyCode' => $company->code,
    'ownerEmail' => $ownerEmail,
    'ownerPassword' => $ownerPassword,
    'assetId' => $asset->id,
    'assetCode' => $asset->asset_code,
    'assetName' => $asset->name,
], JSON_THROW_ON_ERROR);
