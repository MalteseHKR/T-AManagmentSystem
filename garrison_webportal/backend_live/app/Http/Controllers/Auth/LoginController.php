<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Models\UserInformation;

class LoginController extends Controller
{
    protected $redirectTo = '/dashboard';

    protected function username()
    {
        return 'user_email';
    }

    public function showLoginForm()
    {
        return view('credentials.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        // Manually check the credentials
        $user = UserInformation::where('user_email', $credentials['email'])->first();

        if ($user) {
            if (Hash::check($credentials['password'], $user->password)) {
                if ($user->user_active) {
                    // Log the user in manually
                    Auth::login($user);

                    // Authentication passed...
                    Log::info('User authenticated successfully.');
                    return redirect()->intended('dashboard');
                } else {
                    // User is not active
                    return redirect()->route('inactive');
                }
            } elseif ($user->password === $credentials['password']) {
                // Password is in plaintext, hash it now
                $user->password = Hash::make($credentials['password']);
                $user->save();

                if ($user->user_active) {
                    // Log the user in manually
                    Auth::login($user);

                    // Authentication passed...
                    Log::info('User authenticated successfully.');
                    return redirect()->intended('dashboard');
                } else {
                    // User is not active
                    return redirect()->route('inactive');
                }
            }
        }

        Log::warning('Authentication failed for user: ' . $request->input('email'));

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
