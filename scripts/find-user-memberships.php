<?php
// scripts/find-user-memberships.php
// Usage: php scripts/find-user-memberships.php <email>
$email = $argv[1] ?? null;
if (! $email) {
    echo "Usage: php scripts/find-user-memberships.php <email>\n";
    exit(1);
}

require __DIR__ . "/../backend/vendor/autoload.php";
$app = require_once __DIR__ . "/../backend/bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

$email = (string) $email;
$user = User::where('email', $email)->first();
if (! $user) {
    echo json_encode(['found' => false, 'email' => $email], JSON_PRETTY_PRINT) . PHP_EOL;
    exit(0);
}

$members = [];
if (method_exists($user, 'companyMemberships')) {
    $members = $user->companyMemberships()->get(['company_id','role','status'])->toArray();
}

$out = [
    'found' => true,
    'userId' => $user->id,
    'email' => $user->email,
    'memberships' => $members,
];

echo json_encode($out, JSON_PRETTY_PRINT) . PHP_EOL;
