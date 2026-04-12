<?php
require 'vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    \App\Models\User::unguard();
    $user = \App\Models\User::first();
    \Illuminate\Support\Facades\Auth::login($user);

    echo "[TEST 10] HcmPayrollThrBatchController@postPayroll\n";
    $request10 = Illuminate\Http\Request::create('/v1/hcm/payroll/thr-batch/post-payroll', 'POST', [
        'calendarYear' => 2026
    ]);
    $request10->setUserResolver(function() use ($user) { return $user; });
    
    $controller = $app->make(\App\Http\Controllers\Api\HcmPayrollThrBatchController::class);
    $response10 = $controller->postPayroll($request10);
    echo "Status: " . $response10->getStatusCode() . "\n";
    echo $response10->getContent() . "\n";

} catch (\Throwable $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}
