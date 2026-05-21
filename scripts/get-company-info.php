<?php
// scripts/get-company-info.php
// Usage: php scripts/get-company-info.php <companyId>
$companyId = $argv[1] ?? null;
if (! $companyId) {
    echo "Usage: php scripts/get-company-info.php <companyId>\n";
    exit(1);
}

require __DIR__ . "/../backend/vendor/autoload.php";
$app = require_once __DIR__ . "/../backend/bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Company;

$company = Company::query()->find((int) $companyId);
if (! $company) {
    echo json_encode(['found' => false, 'companyId' => (int) $companyId], JSON_PRETTY_PRINT) . PHP_EOL;
    exit(0);
}

echo json_encode([
    'found' => true,
    'id' => $company->id,
    'name' => $company->name ?? null,
    'code' => $company->code ?? null,
    'slug' => $company->slug ?? null,
], JSON_PRETTY_PRINT) . PHP_EOL;
