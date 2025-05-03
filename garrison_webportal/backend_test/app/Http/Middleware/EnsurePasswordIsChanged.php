<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EnsurePasswordIsChanged
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $userId = session('user_id') ?? Auth::id();

        if ($userId) {
            $passwordReset = DB::table('login')->where('user_login_id', $userId)->value('password_reset');

            if ($passwordReset) {
                return redirect()->route('auth.first-change')->with('warning', 'You must change your password before accessing the system.');
            }
        }

        return $next($request);
    }
}