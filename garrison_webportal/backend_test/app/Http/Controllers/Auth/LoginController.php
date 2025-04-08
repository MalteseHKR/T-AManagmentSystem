<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class LoginController extends Controller
{
    protected $redirectTo = RouteServiceProvider::HOME;
    protected $maxAttempts = 4;
    protected $lockoutTime = 15;

    public function showLoginForm()
    {
        return view('login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $loginRecord = DB::table('login')->where('email', $request->email)->first();

        if ($loginRecord && $this->isUserLockedOut($loginRecord)) {
            $lastAttempt = Carbon::parse($loginRecord->last_login_attempt);
            $lockoutEnds = $lastAttempt->addMinutes($this->lockoutTime);
            $secondsRemaining = now()->diffInSeconds($lockoutEnds);
            $formattedTime = sprintf("%02d:%02d", floor($secondsRemaining / 60), $secondsRemaining % 60);

            session(['lockout_time_seconds' => $secondsRemaining]);

            return redirect()->back()
                ->withInput($request->only('email', 'remember'))
                ->with('account_locked', true)
                ->with('lockout_time', $formattedTime)
                ->with('error', "Too many login attempts. Please try again in {$formattedTime}.");
        }

        if (!$this->attemptAuthentication($request)) {
            $this->incrementLoginAttempts($request->email);
            $remainingAttempts = $loginRecord ? ($this->maxAttempts - ($loginRecord->login_attempts + 1)) : ($this->maxAttempts - 1);

            return redirect()->back()
                ->withInput($request->only('email', 'remember'))
                ->with('attempts_left', $remainingAttempts)
                ->with('error', 'These credentials do not match our records.');
        }

        if ($loginRecord) {
            DB::table('login')->where('email', $request->email)->update(['login_attempts' => 0]);
        }

        $user = DB::table('login')->where('email', $request->email)->first();

        if (!$user) {
            Log::error('User record not found after authentication', ['email' => $request->email]);
            Auth::logout();
            return redirect()->route('login')->with('error', 'An error occurred. Please try again.');
        }

        session(['user_id' => $user->user_login_id]);

        if ($user->mfa_enabled) {
            session(['mfa_required' => true, 'mfa_email' => $user->email]);
            Auth::logout();
            return redirect()->route('mfa.verify')->with('info', 'Please verify your identity using your authenticator app.');
        }

        if ($user->password_reset) {
            return redirect()->route('auth.change-password')->with('warning', 'You need to change your password before continuing.');
        }

        $request->session()->regenerate();
        return redirect()->intended($this->redirectTo);
    }

    protected function attemptAuthentication(Request $request)
    {
        $user = DB::table('login')->where('email', $request->email)->first();

        if ($user && Hash::check($request->password, $user->user_login_pass)) {
            Auth::loginUsingId($user->user_login_id, $request->filled('remember'));
            return true;
        }

        return false;
    }

    protected function incrementLoginAttempts($email)
    {
        $loginRecord = DB::table('login')->where('email', $email)->first();

        if ($loginRecord) {
            DB::table('login')->where('email', $email)->update([
                'login_attempts' => DB::raw('login_attempts + 1'),
                'last_login_attempt' => now()
            ]);
        }
    }

    protected function isUserLockedOut($loginRecord)
    {
        if ($loginRecord->login_attempts < $this->maxAttempts) return false;

        if ($loginRecord->last_login_attempt) {
            $lastAttempt = Carbon::parse($loginRecord->last_login_attempt);
            $lockoutEnds = $lastAttempt->addMinutes($this->lockoutTime);

            if (now()->gt($lockoutEnds)) {
                DB::table('login')->where('email', $loginRecord->email)->update(['login_attempts' => 0]);
                return false;
            }

            return true;
        }

        return false;
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }

    public function showMfaForm()
    {
        if (!session('mfa_required')) return redirect()->route('login');
        return view('auth.mfa.verify');
    }

    public function verifyMfa(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6|regex:/^[0-9]+$/'
        ]);

        if (!session('mfa_required') || !session('mfa_email')) {
            return redirect()->route('login');
        }

        $email = session('mfa_email');
        $user = DB::table('login')->where('email', $email)->first();

        if (!$user) return redirect()->route('login')->with('error', 'User not found');

        $google2faService = app(\App\Services\Google2FAService::class);

        if ($google2faService->verifyKey($user->google2fa_secret, $request->code)) {
            Auth::loginUsingId($user->user_login_id);
            $request->session()->regenerate();
            session()->forget(['mfa_required', 'mfa_email']);

            if ($user->password_reset) {
                return redirect()->route('auth.change-password')->with('warning', 'You need to change your password before continuing.');
            }

            return redirect()->intended($this->redirectTo);
        }

        return redirect()->back()->withErrors(['code' => 'Invalid verification code. Please try again.']);
    }

    public function showChangePasswordForm()
    {
        return view('auth.change-password');
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:8|confirmed',
        ]);

        $user = Auth::user();
        $loginRecord = DB::table('login')->where('user_login_id', $user->id)->first();

        if (!$loginRecord) {
            return back()->withErrors(['error' => 'User record not found']);
        }

        if (!Hash::check($request->current_password, $loginRecord->user_login_pass)) {
            return back()->withErrors(['current_password' => 'The current password is incorrect']);
        }

        DB::table('login')->where('user_login_id', $user->id)->update([
            'user_login_pass' => Hash::make($request->new_password),
            'password_reset' => 0
        ]);

        return redirect()->route('dashboard')->with('success', 'Password changed successfully');
    }
}
