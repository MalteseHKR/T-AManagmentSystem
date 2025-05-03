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

        // Redirect first-time users to the first-change password page
        if ($user->password_reset) {
            return redirect()->route('auth.first-change')->with('warning', 'You need to change your password before continuing.');
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
        // Check if we're in recovery mode or regular mode
        $isRecoveryMode = $request->has('recovery_mode') && $request->input('recovery_mode') === 'true';
        
        if ($isRecoveryMode) {
            $request->validate([
                'code' => 'required|string'
            ]);
        } else {
            $request->validate([
                'code' => 'required|string|size:6|regex:/^[0-9]+$/'
            ]);
        }

        if (!session('mfa_required') || !session('mfa_email')) {
            return redirect()->route('login');
        }

        $email = session('mfa_email');
        $user = DB::table('login')->where('email', $email)->first();

        if (!$user) return redirect()->route('login')->with('error', 'User not found');

        $google2faService = app(\App\Services\Google2FAService::class);

        // Regular MFA code verification
        if (!$isRecoveryMode && $google2faService->verifyKey($user->google2fa_secret, $request->code)) {
            Auth::loginUsingId($user->user_login_id);
            $request->session()->regenerate();
            session()->forget(['mfa_required', 'mfa_email']);

            if ($user->password_reset) {
                return redirect()->route('auth.change-password')->with('warning', 'You need to change your password before continuing.');
            }

            return redirect()->intended($this->redirectTo);
        }
        // Recovery code verification
        else if ($isRecoveryMode && $this->verifyRecoveryCode($user, $request->code)) {
            Auth::loginUsingId($user->user_login_id);
            $request->session()->regenerate();
            session()->forget(['mfa_required', 'mfa_email']);
            
            // Set a session flag to show recovery code used message
            session(['recovery_code_used' => true]);

            if ($user->password_reset) {
                return redirect()->route('auth.change-password')->with('warning', 'You need to change your password before continuing.');
            }

            return redirect()->intended($this->redirectTo)->with('warning', 
                'You used a recovery code to log in. Please set up MFA on your new device and generate new recovery codes for security.');
        }

        // If verification fails
        if ($isRecoveryMode) {
            return redirect()->back()
                ->withErrors(['code' => 'Invalid recovery code. Please double-check and try again.'])
                ->with('recovery_mode', true);
        } else {
            return redirect()->back()
                ->withErrors(['code' => 'Invalid verification code. Please try again.']);
        }
    }

    /**
     * Verify a recovery code
     */
    protected function verifyRecoveryCode($user, $code)
    {
        if (!$user->recovery_codes) {
            return false;
        }

        $recoveryCodes = json_decode($user->recovery_codes, true);
        
        // Normalize the code by removing spaces and hyphens
        $normalizedCode = preg_replace('/[\s-]+/', '', $code);
        
        // Check for a match in the recovery codes
        foreach ($recoveryCodes as $index => $recoveryCode) {
            $normalizedRecoveryCode = preg_replace('/[\s-]+/', '', $recoveryCode);
            
            if ($normalizedCode === $normalizedRecoveryCode) {
                // Remove the used code
                unset($recoveryCodes[$index]);
                
                // Update recovery codes in the database
                DB::table('login')->where('user_login_id', $user->user_login_id)->update([
                    'recovery_codes' => json_encode(array_values($recoveryCodes))
                ]);
                
                // Log recovery code usage
                Log::info("User {$user->user_login_id} used a recovery code to authenticate");
                
                return true;
            }
        }
        
        return false;
    }

    public function showChangePasswordForm()
    {
        return view('auth.change-password');
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => [
                'required',
                'string',
                'min:8',
                'max:50',
                'regex:/[A-Z]/', // At least one uppercase letter
                'regex:/[a-z]/', // At least one lowercase letter
                'regex:/[0-9]/', // At least one number
                'regex:/[^A-Za-z0-9]/', // At least one special character
                'confirmed',
            ],
        ]);

        $userId = Auth::id();

        if (!$userId) {
            return redirect()->route('login')->with('error', 'Session expired. Please log in again.');
        }

        $loginRecord = DB::table('login')->where('user_login_id', $userId)->first();

        if (!$loginRecord) {
            return back()->withErrors(['error' => 'User record not found']);
        }

        if (!Hash::check($request->current_password, $loginRecord->user_login_pass)) {
            return back()->withErrors(['current_password' => 'The current password is incorrect']);
        }

        DB::table('login')->where('user_login_id', $userId)->update([
            'user_login_pass' => Hash::make($request->new_password),
        ]);

        return redirect()->route('profile.password')->with('success', 'Password changed successfully.');
    }

    public function showFirstTimePasswordChange()
    {
        // Check if the user is logged in and requires a password reset
        if (!session('user_id') || !DB::table('login')->where('user_login_id', session('user_id'))->value('password_reset')) {
            // Redirect to login if unauthorized or password reset is not required
            return redirect()->route('login')->with('error', 'Unauthorized access.');
        }

        // Store a session flag to indicate the user is in the first-time password change flow
        session(['first_time_password_change' => true]);

        return view('auth.first-change');
    }

    public function firstTimeChangePassword(Request $request)
    {
        // Ensure the user is in the first-time password change flow
        if (!session('first_time_password_change')) {
            return redirect()->route('login')->with('error', 'Unauthorized access.');
        }

        $request->validate([
            'new_password' => [
                'required',
                'string',
                'min:8',
                'max:50',
                'regex:/[A-Z]/', 
                'regex:/[a-z]/', 
                'regex:/[0-9]/', 
                'regex:/[^A-Za-z0-9]/', 
                'confirmed',
            ],
        ]);

        $userId = session('user_id');

        if (!$userId) {
            return redirect()->route('login')->with('error', 'Session expired. Please log in again.');
        }

        DB::table('login')->where('user_login_id', $userId)->update([
            'user_login_pass' => Hash::make($request->new_password),
            'password_reset' => 0,
        ]);

        // Clear the session flag after successful password change
        session()->forget('first_time_password_change');

        // Log the user in after password change
        Auth::loginUsingId($userId);
        session()->forget('user_id');

        return redirect()->intended($this->redirectTo)->with('success', 'Password changed successfully.');
    }
}
