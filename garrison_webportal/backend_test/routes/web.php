<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;

// EMERGENCY DEBUG ROUTE
use Illuminate\Support\Facades\Auth;

Route::get('/auth-check', function () {
    return response()->json([
        'Auth::check()' => Auth::check(),
        'Auth::id()' => Auth::id(),
        'session' => session()->all(),
    ]);
});

Route::get('/auth-check', function () {
    return response()->json([
        'Auth::check()' => Auth::check(),
        'Auth::id()' => Auth::id(),
        'User model' => Auth::user(),
        'Session' => session()->all(),
    ]);
});

// Public routes
Route::get('/', function () {
    return view('home');
})->name('home');

Route::get('/home', [HomeController::class, 'index'])->name('home');

// Auth routes
Route::get('/login', function () {
    return view('login');
})->name('login')->middleware('guest');

Route::post('/login', [App\Http\Controllers\Auth\LoginController::class, 'login']);
Route::post('/logout', [App\Http\Controllers\Auth\LoginController::class, 'logout'])->name('logout');

// MFA Authentication routes
Route::get('/mfa/verify', [App\Http\Controllers\Auth\LoginController::class, 'showMfaForm'])->name('mfa.verify');
Route::post('/mfa/verify', [App\Http\Controllers\Auth\LoginController::class, 'verifyMfa'])->name('mfa.verify');

// Add this BEFORE your auth middleware group
Route::get('/login-complete', function() {
    // Log information
    Log::info("Login-complete route accessed", [
        'user_id' => session('user_id'),
        'is_logged_in' => session('is_logged_in'),
        'session_data' => session()->all()
    ]);
    
    // 1. First redirect option - direct redirection to dashboard
    if (session('is_logged_in')) {
        if (session('password_reset')) {
            return redirect('/change-password')->with('message', 'Please change your password');
        } else {
            return redirect('/dashboard')->with('message', 'Login successful');
        }
    }
    
    // If not logged in
    return redirect('/login')->with('error', 'Please log in first');
})->name('login.complete');

// All protected routes in a single middleware group
Route::middleware(['auth'])->group(function () {
    // Dashboard
    Route::get('/dashboard', function (Request $request) {
        Log::info("Dashboard route accessed", [
            'user_id' => session('user_id'),
            'is_logged_in' => session('is_logged_in'),
            'all_session_data' => session()->all()
        ]);
        
        return view('dashboard');
    })->name('dashboard');

    // Employee Management
    Route::get('/employees', [EmployeeController::class, 'index'])->name('employees');
    Route::get('/employees/create', [App\Http\Controllers\CreateEmployeeController::class, 'create'])->name('employees.create');
    Route::post('/employees', [App\Http\Controllers\CreateEmployeeController::class, 'store'])->name('employees.store');
    Route::get('/employee/{id}', [EmployeeController::class, 'show'])->name('employee.profile');

    // Attendance
    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance');
    Route::get('/attendance/dashboard', [AttendanceController::class, 'dashboard'])->name('attendance.dashboard');
    Route::get('/attendance/analytics', [AttendanceController::class, 'analytics'])->name('attendance.analytics');
    Route::get('/attendance/employee/{employeeId}', [AttendanceController::class, 'showEmployeeAttendance'])->name('attendance.employee');
    Route::get('/attendance/employee', [AttendanceController::class, 'attendanceEmployee'])->name('attendance.attendanceE');

    // Announcements
    Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements');
    Route::get('/announcements/create', [AnnouncementController::class, 'create'])->name('announcements.create');
    Route::post('/announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
    Route::get('/announcements/{announcement}/edit', [AnnouncementController::class, 'edit'])->name('announcements.edit');
    Route::put('/announcements/{announcement}', [AnnouncementController::class, 'update'])->name('announcements.update');
    Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy'])->name('announcements.destroy');
    Route::get('/announcements/{id}/delete', [AnnouncementController::class, 'confirmDelete'])->name('announcements.delete');

    // Payroll
    Route::get('/payroll', [PayrollController::class, 'index'])->name('payroll');

    // Leave Management
    Route::get('/leaves', [LeaveController::class, 'index'])->name('leaves');
    Route::get('/leaves/create', [LeaveController::class, 'create'])->name('leaves.create');
    Route::post('/leaves', [LeaveController::class, 'store'])->name('leaves.store');
    Route::put('/leaves/{id}/status', [LeaveController::class, 'updateStatus'])->name('leaves.update-status');

    // Session Management
    Route::post('/session/extend', [App\Http\Controllers\SessionController::class, 'extend'])->name('session.extend');

    // Secure Images
    Route::get('/secure-image/{filename}', function ($filename, Request $request) {
        $path = "/home/softwaredev/garrison-app-server/uploads/" . $filename;
        if (!File::exists($path)) {
            abort(404, 'Image not found');
        }
        return Response::file($path, [
            'Content-Type' => mime_content_type($path),
            'Content-Disposition' => 'inline; filename="'.basename($path).'"'
        ]);
    })->name('secure-image');

    Route::get('/profile-photo/{userId}', [ImageController::class, 'serveProfileImage'])->name('profile.photo');

    // Image Routes - Organized by specificity
    Route::get('/images', [ImageController::class, 'index'])->name('images.index');
    Route::get('/images/test', function() { return view('images.test'); })->name('images.test');
    Route::get('/images/test-connection', [ImageController::class, 'testConnection'])->name('images.test-connection');
    Route::get('/images/debug', [ImageController::class, 'debug'])->name('images.debug');
    Route::get('/images/placeholder', [ImageController::class, 'placeholder'])->name('images.placeholder');
    Route::get('/images/serve/{filename}', [ImageController::class, 'serve'])->name('images.serve');
    Route::get('/images/get/{filename}', [ImageController::class, 'getImage'])->name('images.get');
    Route::get('/images/list-local', [ImageController::class, 'listLocalImages'])->name('images.list.local');
    
    // Wildcard route must be last
    Route::get('/images/{filename}', [ImageController::class, 'serveLocal'])
        ->where('filename', '.*')
        ->name('images.local');

    // Change password routes
    Route::get('/change-password', function(Request $request) {
        Log::info("Change Password form route accessed", [
            'user_id' => session('user_id'),
            'is_logged_in' => session('is_logged_in'),
            'from_url' => url()->previous()
        ]);
        
        return app()->make(App\Http\Controllers\Auth\LoginController::class)->showChangePasswordForm($request);
    })->name('password.change');

    Route::post('/change-password', function(Request $request) {
        Log::info("Change Password submit route accessed", [
            'user_id' => session('user_id'),
            'is_logged_in' => session('is_logged_in')
        ]);
        
        return app()->make(App\Http\Controllers\Auth\LoginController::class)->changePassword($request);
    })->name('password.change.submit');

    // MFA Setup routes (protected by auth)
    Route::get('/profile/2fa', [App\Http\Controllers\MfaController::class, 'index'])->name('mfa.index');
    Route::get('/profile/2fa/setup', [App\Http\Controllers\MfaController::class, 'setup'])->name('mfa.setup');
    Route::post('/profile/2fa/enable', [App\Http\Controllers\MfaController::class, 'enable'])->name('mfa.enable');
    Route::post('/profile/2fa/disable', [App\Http\Controllers\MfaController::class, 'disable'])->name('mfa.disable');
    Route::get('/profile/2fa/recovery-codes', [App\Http\Controllers\MfaController::class, 'showRecoveryCodes'])->name('mfa.recovery-codes');
    Route::get('/profile/2fa/recovery-codes/regenerate', [App\Http\Controllers\MfaController::class, 'regenerateRecoveryCodes'])->name('mfa.regenerate-codes');
});
