<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Support\Facades\Log;
use Illuminate\Auth\AuthenticationException;

class Authenticate extends Middleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string[]  ...$guards
     * @return mixed
     *
     * @throws \Illuminate\Auth\AuthenticationException
     */
    public function handle($request, Closure $next, ...$guards)
    {
        Log::info("Auth middleware running", [
            'url' => $request->url(),
            'path' => $request->path(),
            'session_user_id' => session('user_id'),
            'is_logged_in' => session('is_logged_in') ? 'Yes' : 'No'
        ]);
        
        // Check for custom session authentication
        if (session('is_logged_in') && session('user_id')) {
            Log::info("Auth middleware - Session authentication successful", [
                'user_id' => session('user_id'),
                'user_name' => session('user_name')
            ]);
            
            return $next($request);
        }
        
        // Not authenticated via session, log and throw exception
        Log::warning("Auth middleware - Session authentication failed", [
            'is_logged_in' => session('is_logged_in'),
            'user_id' => session('user_id')
        ]);
        
        // Use Laravel's standard authentication exception
        $this->unauthenticated($request, $guards);
        
        // This line won't be reached, but added for clarity
        return redirect()->route('login');
    }

    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function redirectTo($request)
    {
        if (!$request->expectsJson()) {
            Log::info("Auth middleware redirecting to login", [
                'path' => $request->path()
            ]);
            return route('login');
        }
    }
}