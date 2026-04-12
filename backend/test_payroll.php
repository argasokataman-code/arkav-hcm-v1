<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// Login as User 1
\App\Models\User::unguard();
$app->make('db'); // Init DB
$user = \App\Models\User::first();
\Illuminate\Support\Facades\Auth::login($user);

// Create request to /payroll-run
$request = Illuminate\Http\Request::create('/payroll-run', 'GET');
$response = $kernel->handle($request);

echo "Status: " . $response->getStatusCode() . "\n";
if ($response->getStatusCode() === 500) {
    echo "Exception:\n" . $response->exception . "\n";
} else {
    echo "Success! Length: " . strlen($response->getContent()) . "\n";
}
