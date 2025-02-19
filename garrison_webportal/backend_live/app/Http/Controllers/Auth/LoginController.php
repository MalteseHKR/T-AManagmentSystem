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
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        // Manually check the credentials
        $user = UserInformation::where('user_email', $credentials['email'])->first();

        if ($user && Hash::check($credentials['password'], $user->password)) {
            // Log the user in manually
            Auth::login($user);

            // Authentication passed...
            Log::info('User authenticated successfully.');
            return redirect()->intended('dashboard');
        }

        Log::warning('Authentication failed for user: ' . $request->input('email'));

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ]);
    }
}
