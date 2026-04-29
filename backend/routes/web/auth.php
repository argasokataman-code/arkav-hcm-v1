<?php

use App\Http\Controllers\CustomAuthController;
use Illuminate\Support\Facades\Route;

Route::post('custom-login', [CustomAuthController::class, 'customLogin'])->name('login.custom');
Route::post('custom-registration', [CustomAuthController::class, 'customRegistration'])->name('register.custom');
Route::get('signout', [CustomAuthController::class, 'signOut'])->name('signout');

Route::get('/login', function () {
    return view('auth.login');
})->name('login');
Route::get('/login-2', function () {
    return view('auth.login-2');
})->name('login-2');
Route::get('/login-3', function () {
    return view('auth.login-3');
})->name('login-3');

Route::get('/register', function () {
    return redirect()->route('landing', ['openOnboarding' => 1, 'startMode' => 'pending_payment']);
})->name('register');
Route::get('/register-2', function () {
    return redirect()->route('landing', ['openOnboarding' => 1, 'startMode' => 'pending_payment']);
})->name('register-2');
Route::get('/register-3', function () {
    return redirect()->route('landing', ['openOnboarding' => 1, 'startMode' => 'pending_payment']);
})->name('register-3');

Route::get('/forgot-password', function () {
    return view('auth.forgot-password');
})->name('forgot-password');
Route::post('/forgot-password', [CustomAuthController::class, 'sendPasswordResetLink'])->name('password.email');
Route::get('/forgot-password-2', function () {
    return view('auth.forgot-password-2');
})->name('forgot-password-2');
Route::get('/forgot-password-3', function () {
    return view('auth.forgot-password-3');
})->name('forgot-password-3');

Route::get('/reset-password', function () {
    return redirect()->route('forgot-password');
})->name('reset-password');
Route::get('/reset-password/{token}', [CustomAuthController::class, 'showResetPasswordForm'])->name('password.reset');
Route::post('/reset-password', [CustomAuthController::class, 'updatePassword'])->name('password.update');
Route::get('/reset-password-2', function () {
    return view('auth.reset-password-2');
})->name('reset-password-2');
Route::get('/reset-password-3', function () {
    return view('auth.reset-password-3');
})->name('reset-password-3');

Route::get('/email-verification', function () {
    return view('auth.email-verification');
})->name('email-verification');
Route::get('/email-verification-2', function () {
    return view('auth.email-verification-2');
})->name('email-verification-2');
Route::get('/email-verification-3', function () {
    return view('auth.email-verification-3');
})->name('email-verification-3');

Route::get('/two-step-verification', function () {
    return view('auth.two-step-verification');
})->name('two-step-verification');
Route::get('/two-step-verification-2', function () {
    return view('auth.two-step-verification-2');
})->name('two-step-verification-2');
Route::get('/two-step-verification-3', function () {
    return view('auth.two-step-verification-3');
})->name('two-step-verification-3');

Route::get('/lock-screen', function () {
    return view('auth.lock-screen');
})->name('lock-screen');
Route::post('/lock-screen/verify', [CustomAuthController::class, 'verifyLockScreen'])->name('lock-screen.verify');
