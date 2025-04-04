<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EnsurePasswordChanged
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
        if (Auth::check()) {
            // Get password_reset value from the login table
            $user = Auth::user();
            $loginInfo = DB::table('login')
                ->where('user_id', $user->user_id)
                ->first();
                
            if ($loginInfo && $loginInfo->password_reset == 1) {
                if (!$request->routeIs('password.change') && 
                    !$request->routeIs('password.change.submit') && 
                    !$request->routeIs('logout')) {
                    return redirect()->route('password.change');
                }
            }
        }

        return $next($request);
    }
}