<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LabController;
use App\Http\Controllers\LoginController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return redirect()->route('login');
});

Route::controller(LoginController::class)->group(function () {
    Route::get('/login', 'index')->name('index');
    Route::post('login', 'login')->name('login');
    Route::get('logout', 'logout')->name('logout');
});

Route::middleware(['auth'])->group(function () {

    Route::controller(DashboardController::class)->group(function () {
        Route::get('dashboard', 'index')->name('dashboard');
    });

    Route::resource('lab', LabController::class);
});
Route::get('/check-auth', function () {
    return Auth::guard('web')->check() ? 'Logged in' : 'Not logged in';
});
