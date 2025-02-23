<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\AttendanceController;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/login', function () {
    return view('login');
})->name('login');

Route::post('/login', [App\Http\Controllers\Auth\LoginController::class, 'login']);

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::get('/employees', [EmployeeController::class, 'index'])->name('employees');

    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::get('/attendance/{employeeId}', [AttendanceController::class, 'show'])->name('attendance.show');

    Route::get('/announcements', function () {
        return view('announcements');
    })->name('announcements');

    Route::get('/payroll', [PayrollController::class, 'index'])->name('payroll');

    Route::get('/leaves', [LeaveController::class, 'index'])->name('leaves');

    Route::get('/employee/{id}', [EmployeeController::class, 'show'])->name('employee.profile');
});
