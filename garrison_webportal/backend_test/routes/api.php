<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;

// Public routes
Route::get('/sample-data', function () {
    return response()->json(['message' => 'Hello from Laravel!']);
});

// Test database connection and default user
Route::get('/test-default-user', function () {
    try {
        $user = DB::table('users')->where('email', 'default@example.com')->first();
        return response()->json([
            'message' => 'Default user found',
            'user' => $user ? ['email' => $user->email, 'role' => $user->role] : null
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});

// CSRF token route
Route::get('/csrf-token', function () {
    return Response::json(['csrfToken' => csrf_token()]);
});

// Authentication routes
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// Protected routes
Route::middleware(['auth:sanctum'])->group(function () {
    // User routes
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::get('/user/{id}/information', [UserController::class, 'getUserInformation']);
    
    // Logout route
    Route::post('/logout', [AuthController::class, 'logout']);
});
