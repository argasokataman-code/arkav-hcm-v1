<?php

namespace App\Http\Controllers;

use App\Models\AuthToken;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Hash;
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
           if ($credentials['email']=='admin@example.com' && $credentials['password']=='123456'){
        return redirect()->intended('index')
                        ->withSuccess('Signed in');
        }
        if (Auth::attempt($credentials)) {
            $user = Auth::user();

            // Create API token so browser JS (e.g. /v1/identity/auth/me) works after web login.
            $plainToken = Str::random(64);
            $expiresIn = (int) config('auth.api_token_cookie.ttl_seconds', 3600);
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

            return redirect()->intended('index')
                ->withSuccess('Signed in')
                ->withCookie(cookie(
                    $cookieName,
                    $plainToken,
                    $cookieMinutes,
                    $cookiePath,
                    $cookieDomain,
                    $cookieSecure,
                    true,
                    false,
                    $cookieSameSite
                ));
        }
        return redirect("login")->withErrors('These credentials do not match our records.');
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
         
        return redirect("login")->withSuccess('You have signed-in');
    }


    public function create(array $data)
    {
      return User::create([
        'name' => $data['name'],
        'email' => $data['email'],
        'password' => Hash::make($data['password'])
      ]);
    }    
    

    public function dashboard()
    {
        if(Auth::check()){
            return view('misc.index');
        }
  
        return redirect("login")->withSuccess('You are not allowed to access');
    }
    

    public function signOut() {
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
        
        if (!$user) {
            return redirect('login')->withErrors('Session expired. Please login again.');
        }

        // Verify password using Hash
        if (!Hash::check($request->password, $user->password)) {
            return redirect('lock-screen')->withErrors('Invalid password. Please try again.');
        }

        // Password is correct, regenerate session token for security
        $request->session()->regenerate();

        // Create/refresh API token cookie so JS calls work after lock-screen unlock.
        $plainToken = Str::random(64);
        $expiresIn = (int) config('auth.api_token_cookie.ttl_seconds', 3600);
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

        // Redirect to the intended page or dashboard
        return redirect()->intended('index')
            ->withSuccess('Session unlocked successfully.')
            ->withCookie(cookie(
                $cookieName,
                $plainToken,
                $cookieMinutes,
                $cookiePath,
                $cookieDomain,
                $cookieSecure,
                true,
                false,
                $cookieSameSite
            ));
    }

    public function sendPasswordResetLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        try {
            $status = Password::sendResetLink($request->only('email'));
        } catch (\Throwable $e) {
            $user = User::query()->where('email', $request->email)->first();
            if (! $user) {
                return back()->withErrors(['email' => 'Unable to send reset link.']);
            }

            // Dev fallback: still allow reset flow even if mail transport fails.
            $token = Password::broker()->createToken($user);

            return redirect()->route('password.reset', ['token' => $token, 'email' => $user->email])
                ->with('status', 'Mail service unavailable. Using direct reset link for testing.');
        }

        return $status === Password::RESET_LINK_SENT
            ? back()->with('status', __($status))
            : back()->withErrors(['email' => __($status)]);
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
            'password' => 'required|string|min:8|confirmed',
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
