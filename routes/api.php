<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\DoctorReviewController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\API\General\LabResultsController;
use App\Http\Controllers\API\Innoquest\PanelResultsController;
use App\Http\Controllers\API\PDFController;
use App\Http\Controllers\MasterPanelItemController;
use App\Http\Controllers\PanelCommentController;
use App\Http\Controllers\TestingController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;

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

Route::prefix('review')->controller(DoctorReviewController::class)->group(function () {
    Route::get('/', 'processResult')->name('index');
    Route::get('/formatResponse', 'formatResponse')->name('formatResponse');
});

// Test export age without auth
Route::get('/test/export-age', [ExportController::class, 'exportAge'])->name('test.export.age');
Route::get('/test/export-bt-age', [ExportController::class, 'exportBtAge'])->name('test.export.bt.age');

Route::middleware(['api.auth', 'throttle:1000,1'])->group(function () {
    Route::prefix('result')->group(function () {
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }
        // Lab Results Controller routes (General)
        Route::post('/patient', [LabResultsController::class, 'labResults'])->name('labResults');
        Route::get('/{id}', [LabResultsController::class, 'show'])->name('show');

        // Panel Results Controller routes (Innoquest)
        Route::post('/panel', [PanelResultsController::class, 'panelResults'])->name('panelResults');
        // Route::post('/testPanel', [PanelResultsController::class, 'testPanel'])->name('testPanel');
    });

    Route::prefix('import')->controller(ImportController::class)->group(function () {
        Route::get('/innoquestCodeMapping', 'innoquestCodeMapping')->name('innoquestCodeMapping');
        Route::get('/panels', 'panels')->name('panels');
        Route::get('/results', 'results')->name('results');
        Route::get('/files', 'files')->name('files');
        Route::get('/json', 'json')->name('json');
        Route::get('/deliveryFiles', 'deliveryFiles')->name('deliveryFiles');
        Route::get('/innoquestPanelSequence', 'innoquestPanelSequence')->name('innoquestPanelSequence');
        Route::get('/labNumber', 'labNumber')->name('labNumber');
    });

    Route::prefix('pdf')->controller(PDFController::class)->group(function () {
        Route::post('/export', 'export')->name('export');
    });

    Route::prefix('review')->controller(DoctorReviewController::class)->group(function () {
        Route::post('/sync', 'sync')->name('sync');
    });

    Route::prefix('comment')->controller(PanelCommentController::class)->group(function () {
        Route::get('/update', 'update')->name('update');
    });

    Route::prefix('export')->controller(ExportController::class)->group(function () {
        Route::get('/age', 'exportAge')->name('export.age');
        Route::get('/bt/age', 'exportBtAge')->name('export.bt');
    });
});