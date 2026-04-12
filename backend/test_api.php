<?php
require 'vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$httpKernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::create('/v1/hcm/payroll-periods', 'GET');
$request->headers->set('Accept', 'application/json');

\App\Models\User::unguard();
$user = \App\Models\User::first();
if ($user) {
    \Illuminate\Support\Facades\Auth::login($user);
}

$response = $httpKernel->handle($request);
echo "Status: " . $response->getStatusCode() . "\n";
echo $response->getContent() . "\n";
