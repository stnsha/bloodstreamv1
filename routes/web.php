<?php

use App\Http\Controllers\API\PDFController;
use App\Http\Controllers\API\Testing\SpecialTestController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\LoginController;
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
    Route::get('/login', 'index')->name('login');
    Route::post('login', 'login');
    Route::get('logout', 'logout')->name('logout');
});

// Export routes
Route::get('/export/bp', [ExportController::class, 'exportBp'])->name('export.bp');

Route::get('/special-test', [SpecialTestController::class, 'rerunTestResults'])->name('special-test.rerunTestResults');
Route::get('/special-test-parameter/{testResultId}', [SpecialTestController::class, 'checkParameter'])->name('special-test.checkParameter');

Route::prefix('pdf')->controller(PDFController::class)->group(function () {
    Route::get('/export', 'export')->name('export'); // /{id}generateDummyPDF
    Route::get('/generateDummyPDF/{id}', 'getResultById')->name('generateDummyPDF'); //
    Route::get('/getReviewById/{id}', 'getReviewById')->name('getReviewById');
});

Route::middleware(['auth'])->group(function () {
    Route::get('api', function () {
        $lab_id = session()->get('lab_id');

        return view('apis.index', compact('lab_id'));
    })->name('apis.index');
});
