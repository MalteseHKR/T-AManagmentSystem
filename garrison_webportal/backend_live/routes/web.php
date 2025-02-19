<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;

// Authentication Routes
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Registration Routes
Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
Route::post('/register', [RegisterController::class, 'register']);

// Home Route
Route::get('/', function () {
    return view('index');
})->name('home');

// Protected Routes
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
    
    Route::get('/dashboard/hr', function () {
        return view('dashboard.hr');
    })->name('dashboard.hr');

    Route::get('/dashboard/it', function () {
        return view('dashboard.it');
    })->name('dashboard.it');

    Route::get('/dashboard/generic', function () {
        return view('dashboard.generic');
    })->name('dashboard.generic');

});

Route::get('/inactive', function () {
    return view('auth.inactive');
})->name('inactive');
