<?php
// filepath: /c:/xampp/htdocs/5CS024/garrison/T-AManagmentSystem/garrison_webportal/backend_test/app/Http/Controllers/Auth/LoginController.php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Cache\RateLimiter;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class LoginController extends BaseController
{
    protected $maxAttempts = 4; // Maximum login attempts
    protected $decayMinutes = 15; // Lockout duration in minutes

    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    protected function fireLockoutEvent(Request $request)
    {
        event(new Lockout($request));
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // Check for too many login attempts
        if ($this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);
            $seconds = $this->limiter()->availableIn($this->throttleKey($request));
            
            throw ValidationException::withMessages([
                'email' => ["Too many login attempts. Please try again in " . ceil($seconds / 60) . " minutes."],
            ])->status(429);
        }

        // Get the user manually
        $user = \App\Models\User::where('email', $request->email)->first();

        if ($user) {
            $authenticated = false;
            
            // First try: Check if the password is already hashed and validate with Hash::check
            if (strlen($user->user_login_pass) > 20) { // Likely a hashed password (bcrypt is long)
                if (Hash::check($request->password, $user->user_login_pass)) {
                    $authenticated = true;
                }
            } 
            // Second try: Check if it's a plaintext password that matches
            else if ($request->password === $user->user_login_pass) {
                $authenticated = true;
                
                // Hash the plaintext password for future logins
                $this->hashUserPassword($user, $request->password);
                
                Log::info("Password hashed for user: {$user->email}");
            }
            
            // If authentication was successful with either method
            if ($authenticated) {
                Auth::login($user);
                $request->session()->regenerate();
                $this->limiter()->clear($this->throttleKey($request));
                
                return redirect()->intended('dashboard');
            }
        }

        // Authentication failed
        $this->incrementLoginAttempts($request);

        throw ValidationException::withMessages([
            'email' => ['These credentials do not match our records.'],
        ]);
    }
    
    /**
     * Hash a user's password and save it to the database
     */
    protected function hashUserPassword($user, $plainPassword)
    {
        try {
            $user->user_login_pass = Hash::make($plainPassword);
            $user->save();
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to hash password: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Log the user out of the application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function logout(Request $request)
    {
        $this->guard()->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return $this->loggedOut($request) ?: redirect('/');
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

    /**
     * Get the guard to be used during authentication.
     *
     * @return \Illuminate\Contracts\Auth\StatefulGuard
     */
    protected function guard()
    {
        return Auth::guard();
    }

    /**
     * The user has logged out of the application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    protected function loggedOut(Request $request)
    {
        return redirect('/');
    }
}