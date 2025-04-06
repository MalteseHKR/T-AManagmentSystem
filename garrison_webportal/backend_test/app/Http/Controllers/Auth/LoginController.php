<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Cache\RateLimiter;
use Illuminate\Auth\Events\Lockout;
use App\Services\Google2FAService;

class LoginController extends Controller
{
    protected $maxAttempts = 4;
    protected $decayMinutes = 15;
    protected $google2faService;

    public function __construct()
    {
        $this->middleware('guest')->except(['logout', 'showMfaForm', 'verifyMfa', 'showChangePasswordForm', 'changePassword']);
        $this->google2faService = app(Google2FAService::class);
    }

    protected function fireLockoutEvent(Request $request)
    {
        event(new Lockout($request));
    }

    public function login(Request $request)
    {
        Log::info("==== LOGIN ATTEMPT STARTED ====");
        Log::info("Email provided: " . $request->email);

        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);
            $seconds = $this->limiter()->availableIn($this->throttleKey($request));

            throw ValidationException::withMessages([
                'email' => ["Too many login attempts. Try again in " . ceil($seconds / 60) . " minutes."]
            ])->status(429);
        }

        // Auth::attempt() will use User::getAuthPassword() to verify user_login_pass
        if (Auth::attempt([
            'email' => $request->email,
            'password' => $request->password
        ])) {
            $request->session()->regenerate();

            $user = Auth::user();

            Log::info("Auth successful", [
                'auth_id' => $user->getAuthIdentifier(),
                'user_id' => $user->user_id ?? null,
                'session_id' => session()->getId(),
            ]);

            // Check if MFA is enabled for this user
            if ($this->google2faService->isMfaEnabled($user->user_id)) {
                // Set partial authentication flag
                session([
                    'is_logged_in' => false, // Not fully logged in yet
                    'auth_partial' => true   // Partial authentication complete
                ]);

                Log::info("User has MFA enabled, redirecting to verification", [
                    'user_id' => $user->user_id
                ]);

                return redirect()->route('mfa.verify');
            }

            // Set custom session data (optional)
            session([
                'user_id' => $user->user_id,
                'user_name' => optional($user->userInformation)->user_name ?? 'Unknown',
                'user_email' => $user->email,
                'is_logged_in' => true,
                'password_reset' => $user->password_reset == 1,
            ]);

            // Update login stats
            DB::table('login')->where('email', $request->email)->update([
                'login_attempts' => 0,
                'last_login_attempt' => now()
            ]);

            return redirect('/login-complete');
        }

        Log::warning("Authentication failed", [
            'email' => $request->email
        ]);

        $this->incrementLoginAttempts($request);

        return redirect()->back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => 'These credentials do not match our records.']);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    protected function hasTooManyLoginAttempts(Request $request)
    {
        return $this->limiter()->tooManyAttempts(
            $this->throttleKey($request),
            $this->maxAttempts
        );
    }

    protected function incrementLoginAttempts(Request $request)
    {
        $this->limiter()->hit(
            $this->throttleKey($request),
            $this->decayMinutes * 60
        );
    }

    protected function throttleKey(Request $request)
    {
        return strtolower($request->input('email')) . '|' . $request->ip();
    }

    protected function limiter()
    {
        return app(RateLimiter::class);
    }

    public function showChangePasswordForm()
    {
        if (!session('is_logged_in')) {
            return redirect('/login');
        }

        return view('auth.change-password');
    }

    public function changePassword(Request $request)
    {
        if (!session('is_logged_in')) {
            return redirect('/login');
        }

        $request->validate([
            'new_password' => [
                'required', 'string', 'min:8', 'max:50', 'confirmed',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&#^()_+\-=\[\]{}|;:,.<>?\/\\~`])/'
            ]
        ], [
            'new_password.regex' => 'Password must contain an uppercase letter, number, and special character.'
        ]);

        try {
            $userId = session('user_id');
            $userEmail = session('user_email');

            $updated = DB::table('login')->where('user_id', $userId)->update([
                'user_login_pass' => Hash::make($request->new_password),
                'password_reset' => 0
            ]);

            if (!$updated) {
                $updated = DB::table('login')->where('email', $userEmail)->update([
                    'user_login_pass' => Hash::make($request->new_password),
                    'password_reset' => 0
                ]);
            }

            return redirect('/dashboard')->with('success', 'Password updated successfully.');
        } catch (\Exception $e) {
            Log::error("Failed to update password: " . $e->getMessage());
            return redirect()->back()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    public function showMfaForm()
    {
        if (!session('auth_partial')) {
            return redirect('/login');
        }
        
        return view('auth.mfa.verify');
    }

    public function verifyMfa(Request $request)
    {
        if (!session('auth_partial')) {
            return redirect('/login');
        }
        
        $request->validate([
            'code' => 'required|string'
        ]);
        
        $userId = session('user_id');
        $secretKey = $this->google2faService->getSecretKey($userId);
        
        // Check if input is a 6-digit code (standard MFA) or a recovery code
        $isRecoveryCode = strlen($request->code) > 6;
        
        // Handle recovery code
        if ($isRecoveryCode && $this->google2faService->verifyRecoveryCode($userId, $request->code)) {
            // Complete authentication with recovery code
            session([
                'is_logged_in' => true,
                'auth_partial' => false
            ]);
            
            Log::info("MFA authentication completed with recovery code", [
                'user_id' => $userId
            ]);
            
            return redirect('/login-complete')->with('warning', 'You used a recovery code to log in. One fewer recovery codes remaining.');
        }
        
        // Handle regular 6-digit code
        if (!$isRecoveryCode && $this->google2faService->verifyKey($secretKey, $request->code)) {
            // Complete authentication
            session([
                'is_logged_in' => true,
                'auth_partial' => false
            ]);
            
            Log::info("MFA authentication completed", [
                'user_id' => $userId
            ]);
            
            return redirect('/login-complete');
        }
        
        Log::warning("Failed MFA verification attempt", [
            'user_id' => $userId
        ]);
        
        return redirect()->back()->withErrors([
            'code' => 'Invalid authentication code. Please try again.'
        ]);
    }
}
