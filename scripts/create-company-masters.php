<?php
// scripts/create-company-masters.php
// Usage: php scripts/create-company-masters.php <companyId>
$companyId = $argv[1] ?? null;
if (! $companyId) {
    echo "Usage: php scripts/create-company-masters.php <companyId>\n";
    exit(1);
}

require __DIR__ . "/../backend/vendor/autoload.php";
$app = require_once __DIR__ . "/../backend/bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Department;
use App\Models\Designation;

$companyId = (int) $companyId;

$existingDept = Department::where('company_id', $companyId)->first();
if ($existingDept) {
    echo json_encode(['status' => 'skipped', 'message' => 'department exists', 'department' => $existingDept->toArray()], JSON_PRETTY_PRINT) . PHP_EOL;
} 

if (! $existingDept) {
    $dept = new Department();
    $dept->company_id = $companyId;
    $dept->code = 'AUTO-DEP-' . $companyId . '-1';
    $dept->name = 'Default Department';
    $dept->description = 'Auto-created for bulk upload test';
    $dept->is_active = true;
    $dept->save();
    echo json_encode(['created' => 'department', 'id' => $dept->id], JSON_PRETTY_PRINT) . PHP_EOL;
} else {
    $dept = $existingDept;
}

$existingDesig = Designation::where('company_id', $companyId)->first();
if ($existingDesig) {
    echo json_encode(['status' => 'skipped', 'message' => 'designation exists', 'designation' => $existingDesig->toArray()], JSON_PRETTY_PRINT) . PHP_EOL;
}

if (! $existingDesig) {
    $desig = new Designation();
    $desig->company_id = $companyId;
    $desig->department_id = $dept->id;
    $desig->code = 'AUTO-DES-' . $companyId . '-1';
    $desig->name = 'Default Designation';
    $desig->description = 'Auto-created for bulk upload test';
    $desig->is_active = true;
    $desig->save();
    echo json_encode(['created' => 'designation', 'id' => $desig->id], JSON_PRETTY_PRINT) . PHP_EOL;
} else {
    $desig = $existingDesig;
}

$output = [
    'companyId' => $companyId,
    'departmentId' => $dept->id,
    'designationId' => $desig->id,
];

echo json_encode($output, JSON_PRETTY_PRINT) . PHP_EOL;
