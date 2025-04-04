<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

class DetectLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Detect the user's locale based on their region (e.g., from IP address or user settings)
        // For demonstration purposes, we'll use a hardcoded value
        $region = 'us'; // This should be dynamically detected

        // Set the application locale based on the detected region
        App::setLocale($region);

        return $next($request);
    }
}
