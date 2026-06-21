<?php

require_once __DIR__.'/bootstrap/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\DB;

echo "Registering missing features...\n";

// 1. Add hcm_approval_settings to feature_classifications
DB::table('feature_classifications')->updateOrCreate(
    ['feature_code' => 'hcm_approval_settings'],
    ['tier' => 'addon', 'created_at' => now(), 'updated_at' => now()]
);
echo "✅ Added hcm_approval_settings as addon\n";

// 2. Add timesheet to feature_classifications
DB::table('feature_classifications')->updateOrCreate(
    ['feature_code' => 'timesheet'],
    ['tier' => 'addon', 'created_at' => now(), 'updated_at' => now()]
);
echo "✅ Added timesheet as addon\n";

echo "\n✅ Done! Features are now in feature_classifications\n";
