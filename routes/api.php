<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\API\ResultController;
use App\Http\Controllers\TestingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });

Route::controller(AuthController::class)->group(function () {
    Route::post('/register', 'register')->name('register');
    Route::post('/login', 'login')->name('login');
    Route::post('/logout', 'logout')->name('logout');
});


Route::middleware(['api.auth', 'throttle:1000,1'])->group(function () {
    Route::resource('testing', TestingController::class)->only('index', 'store', 'show', 'update', 'destroy');

    Route::prefix('result')->controller(ResultController::class)->group(function () {
        Route::post('/patient', 'labResults')->name('labResults');
        Route::post('/panel', 'panelResults')->name('panelResults');
        Route::post('/testPanel', 'testPanel')->name('testPanel');
        Route::get('/{id}', 'show')->name('show');
    });

    Route::prefix('import')->controller(ImportController::class)->group(function () {
        Route::get('/innoquestCodeMapping', 'innoquestCodeMapping')->name('innoquestCodeMapping');
    });
});
