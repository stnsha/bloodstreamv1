<?php

use App\Http\Controllers\API\PDFController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\LabController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\PanelController;
use App\Http\Controllers\TestResultController;
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
    Route::get('/login', 'index')->name('login');
    Route::post('login', 'login');
    Route::get('logout', 'logout')->name('logout');
});

// Export routes
Route::get('/export/bp', [ExportController::class, 'exportBp'])->name('export.bp');

// Route::prefix('pdf')->controller(PDFController::class)->group(function () {
//     Route::get('/export', 'export')->name('export'); ///{id}generateDummyPDF
//     Route::get('/generateDummyPDF/{id}', 'getResultById')->name('generateDummyPDF'); //
//     Route::get('/getReviewById/{id}', 'getReviewById')->name('getReviewById');
// });

// Lab Number Results View
// Route::get('results/lab-numbers', [ImportController::class, 'labNumberResults'])->name('results.lab-numbers');

Route::middleware(['auth'])->group(function () {
    Route::get('api', function () {
        $lab_id = session()->get('lab_id');
        return view('apis.index', compact('lab_id'));
    })->name('apis.index');

    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::resource('lab', LabController::class);
    Route::resource('results', TestResultController::class);
    Route::resource('panels', PanelController::class);
});