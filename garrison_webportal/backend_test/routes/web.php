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

    Route::get('/employees', [App\Http\Controllers\EmployeeController::class, 'index'])->name('employees');
    Route::get('/create', [EmployeeController::class, 'create'])->name('create');

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

    Route::get('/payroll', [PayrollController::class, 'index'])->name('payroll');

    Route::get('/leaves', [LeaveController::class, 'index'])->name('leaves');

    Route::get('/employee/{id}', [EmployeeController::class, 'show'])->name('employee.profile');

    Route::post('/extend-session', function (Request $request) {
        $request->session()->migrate(true);
        return response()->json(['message' => 'Session extended']);
    })->name('extend-session');

    // Image routes (order matters!)
    Route::get('/images', [App\Http\Controllers\ImageController::class, 'listImages'])
        ->name('images.index');
    Route::get('/images/test', function() {
        return view('images.test');
    })
        ->name('images.test');
    Route::get('/images/test-connection', [App\Http\Controllers\ImageController::class, 'testConnection'])
        ->name('images.test-connection');
    Route::get('/images/debug', [App\Http\Controllers\ImageController::class, 'debug'])
        ->name('images.debug');
    Route::get('/images/placeholder', [App\Http\Controllers\ImageController::class, 'placeholder'])
        ->name('images.placeholder');

    // This wildcard route must come LAST
    Route::get('/images/{filename}', [App\Http\Controllers\ImageController::class, 'getImage'])
        ->name('images.show');

    // Direct connection test route - temporary for debugging
    Route::get('/sftp-direct-test', function () {
        try {
            // Try direct SSH2 connection first
            if (!function_exists('ssh2_connect')) {
                return 'SSH2 extension not available. Install it with: extension=php_ssh2.dll in php.ini';
            }
            
            $host = '192.168.10.11';
            $port = 22;
            $username = 'softwaredev';
            $password = 'PeakySTC2025!!';
            
            // Try to connect
            echo "Attempting to connect to $host:$port...<br>";
            $connection = @ssh2_connect($host, $port);
            
            if (!$connection) {
                return 'Failed to connect to the SFTP server. Check host and port.';
            }
            
            echo "Connected successfully!<br>";
            echo "Attempting authentication...<br>";
            
            // Try authentication
            if (!@ssh2_auth_password($connection, $username, $password)) {
                return 'Authentication failed. Check username and password.';
            }
            
            echo "Authentication successful!<br>";
            echo "Initializing SFTP subsystem...<br>";
            
            // Initialize SFTP subsystem
            $sftp = @ssh2_sftp($connection);
            if (!$sftp) {
                return 'Could not initialize SFTP subsystem.';
            }
            
            echo "SFTP subsystem initialized!<br>";
            
            // Check if the directory exists
            $dir = '/home/softwaredev/garrison-app-server/uploads';
            $dirpath = "ssh2.sftp://$sftp$dir";
            
            echo "Checking directory: $dir<br>";
            if (!file_exists($dirpath)) {
                return "Directory $dir does not exist on the server.";
            }
            
            echo "Directory exists!<br>";
            echo "Listing files...<br>";
            
            // List directory contents
            $handle = opendir($dirpath);
            if (!$handle) {
                return "Failed to open directory $dir.";
            }
            
            $files = [];
            while (false !== ($entry = readdir($handle))) {
                if ($entry != '.' && $entry != '..') {
                    $files[] = $entry;
                }
            }
            
            closedir($handle);
            
            echo "Files found: " . count($files) . "<br>";
            echo "<pre>";
            print_r($files);
            echo "</pre>";
            
            return "SFTP connection test completed successfully!";
            
        } catch (\Exception $e) {
            return 'Error: ' . $e->getMessage() . '<br>Trace: <pre>' . $e->getTraceAsString() . '</pre>';
        }
    })->name('sftp-direct-test');
});
