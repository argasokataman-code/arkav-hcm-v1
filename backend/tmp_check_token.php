<?php
require __DIR__ . '/vendor/autoload.php';
use App\Models\AuthToken;
$lines = file('/tmp/ck_api.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$cookie = '';
foreach ($lines as $l) {
    if (strpos($l, 'arcav_access_token') !== false) {
        $p = preg_split('/\s+/', trim($l));
        $cookie = $p[6] ?? '';
        break;
    }
}
echo 'COOKIE=' . $cookie . PHP_EOL;
if (! $cookie) {
    echo 'NO_COOKIE' . PHP_EOL;
    exit;
}
$hash = hash('sha256', $cookie);
$t = AuthToken::where('token_hash', $hash)->first();
if (! $t) {
    echo 'NOT_IN_DB' . PHP_EOL;
} else {
    echo 'FOUND id=' . $t->id . ' user_id=' . $t->user_id . ' expires_at=' . $t->expires_at . PHP_EOL;
}
