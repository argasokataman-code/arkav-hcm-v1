<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
   echo "Testing View...\n";
   $html = view('payroll-run')->render();
   echo "View rendered successfully. Length: " . strlen($html) . "\n";
} catch (\Throwable $e) {
   echo "ERROR: " . $e->getMessage() . "\n" . $e->getFile() . ":" . $e->getLine() . "\n";
}
