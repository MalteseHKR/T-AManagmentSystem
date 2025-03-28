<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Carbon;

class ExtendedSessionTimeout
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if ($request->session()->has('_extended_lifetime')) {
            $extendedTime = $request->session()->get('_extended_lifetime');
            
            // If the extended time is still in the future
            if ($extendedTime > now()) {
                // Set the session to expire at the extended time
                config(['session.lifetime' => now()->diffInMinutes($extendedTime)]);
            } else {
                // Extended time has passed, remove it
                $request->session()->forget('_extended_lifetime');
            }
        }
        
        return $next($request);
    }
}