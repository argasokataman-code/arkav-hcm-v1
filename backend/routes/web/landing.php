<?php

use App\Http\Controllers\PublicLandingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicLandingController::class, 'index'])->name('root');

Route::get('/landing', [PublicLandingController::class, 'index'])->name('landing');

Route::get('/trial', function (Request $request) {
    $query = ['openOnboarding' => 1];

    $packageId = trim((string) $request->query('packageId', ''));
    if ($packageId !== '') {
        $query['package'] = $packageId;
    }

    $startMode = trim((string) $request->query('startMode', ''));
    if (in_array($startMode, ['trial', 'pending_payment'], true)) {
        $query['startMode'] = $startMode;
    }

    return redirect()->route('landing', $query);
})->name('trial');

Route::get('/api-docs', function () {
    return view('api-docs.swagger');
})->name('api-docs');

Route::get('/api-docs/openapi.yaml', function (Request $request) {
    $path = base_path('../docs/api/openapi.yaml');
    if (! is_file($path)) {
        abort(404);
    }

    $raw = file_get_contents($path);
    if (! is_string($raw) || $raw === '') {
        abort(500);
    }

    $origin = $request->getSchemeAndHttpHost();

    $serversBlock = "servers:\n"
        ."  - url: {$origin}\n"
        ."    description: Auto-detected (current host)\n"
        ."  - url: http://127.0.0.1:8007\n"
        ."    description: Local backend (./run.sh)\n"
        ."  - url: http://127.0.0.1:5179\n"
        ."    description: Local frontend proxy (optional)\n";

    $patched = preg_replace('/^servers:\n.*?^tags:\n/ms', $serversBlock."tags:\n", $raw);
    if (! is_string($patched) || $patched === '') {
        $patched = $raw;
    }

    return response($patched, 200, [
        'Content-Type' => 'application/yaml; charset=utf-8',
        'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
    ]);
})->name('api-docs.spec');
