<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SessionController extends Controller
{
    /**
     * Extend the current session lifetime
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function extend(Request $request)
    {
        try {
            // Get current session lifetime in minutes
            $lifetime = config('session.lifetime', 120);
            
            // Extend session by 60 minutes (1 hour)
            $request->session()->put('_extended_lifetime', now()->addMinutes(60));
            
            // Regenerate session ID for security
            $request->session()->regenerate();
            
            return response()->json([
                'success' => true,
                'message' => 'Session extended successfully',
                'new_expiry' => now()->addMinutes(60)->timestamp * 1000 // Convert to milliseconds for JS
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to extend session: ' . $e->getMessage()
            ], 500);
        }
    }
}