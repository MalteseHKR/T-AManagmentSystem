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

        if ($this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);
            $seconds = $this->limiter()->availableIn($this->throttleKey($request));
            
            throw ValidationException::withMessages([
                'email' => ["Too many login attempts. Please try again in " . ceil($seconds / 60) . " minutes."],
            ])->status(429);
        }

        if (Auth::attempt($request->only('email', 'password'))) {
            $request->session()->regenerate();
            $this->limiter()->clear($this->throttleKey($request));
            
            return redirect()->intended('dashboard');
        }

        $this->incrementLoginAttempts($request);

        throw ValidationException::withMessages([
            'email' => ['These credentials do not match our records.'],
        ]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        
        return redirect('/login')->with('status', 'You have been logged out successfully.');
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
}