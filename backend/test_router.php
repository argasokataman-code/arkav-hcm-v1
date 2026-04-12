<?php
require 'vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

\App\Models\User::unguard();
$app->make('db'); // Init DB
$user = \App\Models\User::first();
\Illuminate\Support\Facades\Auth::login($user);

echo "[TEST END-TO-END via Router]\n";
$request = Illuminate\Http\Request::create('/v1/hcm/payroll-periods', 'GET');
// Setup session if needed for web guards, but this is API route.
// Wait, API route might need Authorization header. We will use actingAs if this is a test.
// Since we can't easily do actingsAs here, we pass the user session.
$request->setUserResolver(function() use ($user) { return $user; });

$response = $kernel->handle($request);
echo "Status: " . $response->getStatusCode() . "\n";
echo "Content: " . $response->getContent() . "\n";
if ($response->getStatusCode() === 500) {
    if (isset($response->exception)) {
        echo "Exception: " . $response->exception->getMessage() . "\n";
        echo $response->exception->getTraceAsString();
    }
}
