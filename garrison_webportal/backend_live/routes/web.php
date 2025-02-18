<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserInformationController;
use App\Http\Controllers\Auth\LoginController;

Route::get('/', function () {
    return view('user_information.index');
});
Route::post('/login', [LoginController::class, 'login'])->name('login');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware('auth');
