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
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;

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

// Password change routes
Route::middleware(['auth'])->group(function () {
    Route::get('/change-password', [App\Http\Controllers\Auth\LoginController::class, 'showChangePasswordForm'])
        ->name('password.change');
    Route::post('/change-password', [App\Http\Controllers\Auth\LoginController::class, 'changePassword'])
        ->name('password.change.submit');
});

// Protected routes
Route::middleware(['auth', 'password.changed'])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::get('/employees', [App\Http\Controllers\EmployeeController::class, 'index'])->name('employees');
    Route::get('/create', [App\Http\Controllers\CreateEmployeeController::class, 'create'])->name('create');
    Route::post('/employees', [App\Http\Controllers\CreateEmployeeController::class, 'store'])->name('employees');

    // GET route for displaying the form
    Route::get('/create', [App\Http\Controllers\CreateEmployeeController::class, 'create'])->name('create');

    // POST route for handling form submission
    Route::post('/create', [App\Http\Controllers\CreateEmployeeController::class, 'store'])->name('create.store');

    // Attendance routes
    Route::get('/attendance', [App\Http\Controllers\AttendanceController::class, 'index'])->name('attendance');
    Route::get('/attendance/dashboard', [App\Http\Controllers\AttendanceController::class, 'dashboard'])->name('attendance.dashboard');
    Route::get('/attendance/analytics', [App\Http\Controllers\AttendanceController::class, 'analytics'])->name('attendance.analytics');
    Route::get('/attendance/employee/{employeeId}', [App\Http\Controllers\AttendanceController::class, 'showEmployeeAttendance'])->name('attendance.employee');

    Route::get('/attendance/employee', [AttendanceController::class, 'attendanceEmployee'])
        ->name('attendance.attendanceE');

    Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements');
    Route::get('/announcements/create', [AnnouncementController::class, 'create'])->name('announcements.create');
    Route::post('/announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
    Route::get('/announcements/{announcement}/edit', [AnnouncementController::class, 'edit'])->name('announcements.edit');
    Route::put('/announcements/{announcement}', [AnnouncementController::class, 'update'])->name('announcements.update');
    Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy'])->name('announcements.destroy');

    // Add this route for the delete confirmation page
    Route::get('/announcements/{id}/delete', [App\Http\Controllers\AnnouncementController::class, 'confirmDelete'])
        ->name('announcements.delete')
        ->middleware('auth');

    Route::get('/payroll', [PayrollController::class, 'index'])->name('payroll');

    // Leave routes
    Route::get('/leaves', [LeaveController::class, 'index'])->name('leaves');
    Route::get('/leaves/create', [LeaveController::class, 'create'])->name('leaves.create');
    Route::post('/leaves', [LeaveController::class, 'store'])->name('leaves.store');
    Route::put('/leaves/{id}/status', [LeaveController::class, 'updateStatus'])->name('leaves.update-status');

    Route::get('/employee/{id}', [EmployeeController::class, 'show'])->name('employee.profile');

    Route::post('/extend-session', function (Request $request) {
        $request->session()->migrate(true);
        return response()->json(['message' => 'Session extended']);
    })->name('extend-session');

// 🔒 Secure image access route (Only for logged-in users)
Route::middleware(['auth'])->group(function () {
    Route::get('/secure-image/{filename}', function ($filename, Request $request) {
        // Define the real storage path
        $path = "/home/softwaredev/garrison-app-server/uploads/" . $filename;

        // Check if file exists
        if (!File::exists($path)) {
            abort(404, 'Image not found');
        }

        // Serve the image with correct headers
        return Response::file($path, [
            'Content-Type' => mime_content_type($path),
            'Content-Disposition' => 'inline; filename="'.basename($path).'"'
        ]);
    })->name('secure-image');
    })->middleware('auth');

Route::get('/profile-photo/{userId}', [App\Http\Controllers\ImageController::class, 'serveProfileImage'])
    ->middleware('auth')
    ->name('profile.photo');


    // Image routes - keep these together and properly ordered
    Route::get('/images', [App\Http\Controllers\ImageController::class, 'index'])->name('images.index');
    Route::get('/images/test', function() {
        return view('images.test');
    })->name('images.test');
    Route::get('/images/test-connection', [App\Http\Controllers\ImageController::class, 'testConnection'])
        ->name('images.test-connection');
    Route::get('/images/debug', [App\Http\Controllers\ImageController::class, 'debug'])
        ->name('images.debug');
    Route::get('/images/placeholder', [App\Http\Controllers\ImageController::class, 'placeholder'])
        ->name('images.placeholder');
    Route::get('/images/serve/{filename}', [App\Http\Controllers\ImageController::class, 'serve'])
        ->name('images.serve');
    Route::get('/images/get/{filename}', [App\Http\Controllers\ImageController::class, 'getImage'])
        ->name('images.get');
    
    // Local image routes

    Route::get('/images/{filename}', [ImageController::class, 'serveLocal'])
    	->where('filename', '.*')
    	->name('images.local');

    Route::get('/images/{filename}', [ImageController::class, 'serveLocal'])
    	->where('filename', '.*')
    	->name('images.local');
    Route::get('/images/list-local', [App\Http\Controllers\ImageController::class, 'listLocalImages'])
        ->name('images.list.local');

    // This wildcard route must come LAST - it will catch any other image paths
    Route::get('/images/{filename}', [App\Http\Controllers\ImageController::class, 'serve'])
        ->where('filename', '.*')
        ->name('images.show');


    // Add these routes if they don't exist
    Route::get('/employees/create', [App\Http\Controllers\CreateEmployeeController::class, 'create'])->name('employees.create');
    Route::post('/employees', [App\Http\Controllers\CreateEmployeeController::class, 'store'])->name('employees');

    // Add this route to your web.php file
    Route::post('/session/extend', [App\Http\Controllers\SessionController::class, 'extend'])->name('session.extend');
});
