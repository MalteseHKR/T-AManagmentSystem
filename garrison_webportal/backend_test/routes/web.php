<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Http\Request;

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

// Protected routes
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::get('/employees', [EmployeeController::class, 'index'])->name('employees');
    Route::get('/create', [EmployeeController::class, 'create'])->name('create');

    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance');
    Route::get('/attendance/dashboard', [AttendanceController::class, 'dashboard'])->name('attendance.dashboard');
    Route::get('/attendance/analytics', [App\Http\Controllers\AttendanceController::class, 'analytics'])
        ->name('attendance.analytics')
        ->middleware('auth');
    Route::get('/attendance/employee', [AttendanceController::class, 'attendanceEmployee'])
        ->name('attendance.attendanceE');
    Route::get('/attendance/employee/{employeeId}', [AttendanceController::class, 'showEmployeeAttendance'])
        ->name('attendance.employee');

    Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements');
    Route::get('/announcements/create', [AnnouncementController::class, 'create'])->name('announcements.create');
    Route::post('/announcements', [AnnouncementController::class, 'store'])->name('announcements.store');

    Route::get('/payroll', [PayrollController::class, 'index'])->name('payroll');

    Route::get('/leaves', [LeaveController::class, 'index'])->name('leaves');

    Route::get('/employee/{id}', [EmployeeController::class, 'show'])->name('employee.profile');

    Route::post('/extend-session', function (Request $request) {
        $request->session()->migrate(true);
        return response()->json(['message' => 'Session extended']);
    })->name('extend-session');
});
