<?php
// scripts/inspect-company-masters.php
// Usage: php scripts/inspect-company-masters.php <companyId>
$companyId = $argv[1] ?? null;
if (! $companyId) {
    echo "Usage: php scripts/inspect-company-masters.php <companyId>\n";
    exit(1);
}

require __DIR__ . "/../backend/vendor/autoload.php";
$app = require_once __DIR__ . "/../backend/bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Department;
use App\Models\Designation;

$companyId = (int) $companyId;
$departments = Department::query()->where('company_id', $companyId)->get(['id','name','code','company_id','is_active'])->toArray();
$depsGlobal = Department::query()->whereNull('company_id')->get(['id','name','code','company_id','is_active'])->toArray();
$designationCount = Designation::query()->whereHas('department', function ($q) use ($companyId) { $q->where('company_id', $companyId); })->count();
$designations = Designation::query()->where('company_id', $companyId)->get(['id','name','code','department_id','company_id','is_active'])->toArray();
$designationsGlobal = Designation::query()->whereNull('company_id')->get(['id','name','code','department_id','company_id','is_active'])->toArray();

$output = [
    'companyId' => $companyId,
    'departmentCountForCompany' => count($departments),
    'departmentSampleForCompany' => array_slice($departments, 0, 10),
    'departmentCountGlobalNull' => count($depsGlobal),
    'departmentSampleGlobalNull' => array_slice($depsGlobal, 0, 10),
    'designationCountForCompany' => $designationCount,
    'designationSampleForCompany' => array_slice($designations, 0, 10),
    'designationCountGlobalNull' => count($designationsGlobal),
    'designationSampleGlobalNull' => array_slice($designationsGlobal, 0, 10),
];

echo json_encode($output, JSON_PRETTY_PRINT);
