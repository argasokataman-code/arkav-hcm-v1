<?php

namespace App\Http\Controllers;

use App\Models\AuthToken;
use App\Models\User;
use Hash;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Session;

class CustomAuthController extends Controller
{
    public function index()
    {

        return view('auth.login');
    }

    public function customLogin(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ],
            [
                'email.required' => 'Email is required',
                'password.required' => 'Password is required',
            ]
        );
        $credentials = $request->only('email', 'password');
        if (Auth::attempt($credentials)) {
            $user = Auth::user();

            // Create API token so browser JS (e.g. /v1/identity/auth/me) works after web login.
            // Do NOT revoke previous tokens — let them expire naturally.
            // Aggressive token cleanup causes issues when requests reference old session tokens.
            $plainToken = Str::random(64);
            $expiresIn = (int) config('auth.api_token_cookie.ttl_seconds', 3600);
            $token = AuthToken::create([
                'user_id' => $user->id,
                'token_hash' => hash('sha256', $plainToken),
                'expires_at' => now()->addSeconds($expiresIn),
            ]);

            // DO NOT store in session - session persistence is unreliable across requests.
            // Rely on browser cookie + /api-token endpoint to manage tokens.

            $cookieName = (string) config('auth.api_token_cookie.name', 'arcav_access_token');
            $cookiePath = (string) config('auth.api_token_cookie.path', '/');
            $cookieDomain = config('auth.api_token_cookie.domain') ?: null;
            $cookieSecure = (bool) config('auth.api_token_cookie.secure', false);
            $cookieSameSite = (string) config('auth.api_token_cookie.same_site', 'lax');
            $cookieMinutes = (int) ceil($expiresIn / 60);

            $isAdmin = $user->isGlobalHcmAdmin() || $user->isHcmAdmin();
            $afterLoginCookie = cookie(
                $cookieName,
                $plainToken,
                $cookieMinutes,
                $cookiePath,
                $cookieDomain,
                $cookieSecure,
                true,
                false,
                $cookieSameSite
            );

            if (! $isAdmin) {
                // Employee/member: always land on employee dashboard, never admin pages.
                return redirect('employee-dashboard')
                    ->withSuccess('Signed in')
                    ->withCookie($afterLoginCookie);
            }

            return redirect()->intended('index')
                ->withSuccess('Signed in')
                ->withCookie($afterLoginCookie);
        }

        return redirect('login')->withErrors('These credentials do not match our records.');
    }

    public function registration()
    {
        return view('auth.register');
    }

    public function customRegistration(Request $request)
    {
        $request->validate([
            'name' => 'required|min:5|max:30',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6',
            'confirmpassword' => 'required|min:6',

        ],
            [
                'name.required' => 'Userame is required',
                'email.required' => 'Email is required',
                'password.required' => 'Password is required',
                'confirmpassword.required' => 'confirmpassword is required',

            ]
        );

        $data = $request->all();
        $check = $this->create($data);

        return redirect('login')->withSuccess('You have signed-in');
    }

    public function create(array $data)
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);
    }

    public function dashboard()
    {
        if (Auth::check()) {
            return view('misc.index');
        }

        return redirect('login')->withSuccess('You are not allowed to access');
    }

    public function signOut()
    {
        Session::flush();
        Auth::logout();

        return Redirect('login');
    }

    public function verifyLockScreen(Request $request)
    {
        $request->validate([
            'password' => 'required|string|min:1',
        ], [
            'password.required' => 'Password is required to unlock your session.',
        ]);

        $user = auth()->user();

        if (! $user) {
            return redirect('login')->withErrors('Session expired. Please login again.');
        }

        // Verify password using Hash
        if (! Hash::check($request->password, $user->password)) {
            return redirect('lock-screen')->withErrors('Invalid password. Please try again.');
        }

        // Password is correct, regenerate session token for security
        $request->session()->regenerate();

        // Create/refresh API token cookie so JS calls work after lock-screen unlock.
        // Revoke previous tokens first — enforce single active token per user.
        $plainToken = Str::random(64);
        $expiresIn = (int) config('auth.api_token_cookie.ttl_seconds', 3600);
        AuthToken::where('user_id', $user->id)->delete();
        AuthToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addSeconds($expiresIn),
        ]);
        $cookieName = (string) config('auth.api_token_cookie.name', 'arcav_access_token');
        $cookiePath = (string) config('auth.api_token_cookie.path', '/');
        $cookieDomain = config('auth.api_token_cookie.domain') ?: null;
        $cookieSecure = (bool) config('auth.api_token_cookie.secure', false);
        $cookieSameSite = (string) config('auth.api_token_cookie.same_site', 'lax');
        $cookieMinutes = (int) ceil($expiresIn / 60);

        // Redirect to the intended page or role-appropriate dashboard
        $unlockCookie = cookie($cookieName, $plainToken, $cookieMinutes, $cookiePath, $cookieDomain, $cookieSecure, true, false, $cookieSameSite);
        $isAdmin = $user->isGlobalHcmAdmin() || $user->isHcmAdmin();

        if (! $isAdmin) {
            return redirect('employee-dashboard')
                ->withSuccess('Session unlocked successfully.')
                ->withCookie($unlockCookie);
        }

        return redirect()->intended('index')
            ->withSuccess('Session unlocked successfully.')
            ->withCookie($unlockCookie);
    }

    public function sendPasswordResetLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with('status', __($status));
        }

        return back()->withErrors(['email' => __($status)]);
    }

    public function showResetPasswordForm(Request $request, string $token)
    {
        return view('reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => ['required', 'string', 'confirmed',
                'regex:/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)[A-Za-z\d@$!%*?&._-]{8,64}$/'],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', __($status))
            : back()->withErrors(['email' => __($status)]);
    }
}
