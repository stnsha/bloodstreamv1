<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\Fixes\HotFixController;
use App\Http\Controllers\API\Nexus\IntegrationController;
use App\Http\Controllers\API\General\LabResultsController;
use App\Http\Controllers\API\Innoquest\PanelResultsController;
use App\Http\Controllers\API\ODB\BloodTestController;
use App\Http\Controllers\API\Innoquest\PDFController;
use App\Http\Controllers\API\ConsultCall\ClinicalConditionController;
use App\Http\Controllers\API\ConsultCall\ConsultCallAuthController;
use App\Http\Controllers\API\ConsultCall\ConsultCallController;
use App\Http\Controllers\API\ConsultCall\ConsultCallFollowUpController;
use App\Http\Controllers\API\ConsultCall\StatusLibraryController;
use App\Http\Controllers\API\Testing\SpecialTestController;
use App\Http\Controllers\API\Webhook\AIResultController;
use App\Http\Controllers\API\Export\DynamicExportController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\PanelCommentController;
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

// Authentication routes - stricter rate limiting (60/min) to prevent brute force
Route::middleware(['throttle:auth'])->controller(AuthController::class)->group(function () {
    Route::post('/register', 'register')->name('register');
    Route::post('/login', 'login')->name('login');
    Route::post('/logout', 'logout')->name('logout');
});

// Test export age without auth
Route::get('/test/export-age', [ExportController::class, 'exportAge'])->name('test.export.age');
Route::get('/test/export-bt-age', [ExportController::class, 'exportBtAge'])->name('test.export.bt.age');

// Webhook routes (secured with webhook.auth middleware)
Route::prefix('webhook')->group(function () {
    Route::post('/ai-result', [AIResultController::class, 'store'])
        ->middleware('webhook.auth')
        ->name('webhook.ai-result');
});

// High-volume lab result endpoints - 500/minute to handle batch processing
Route::middleware(['api.auth', 'throttle:lab-results'])->group(function () {
    Route::prefix('result')->group(function () {
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }
        // Lab Results Controller routes (General)
        Route::post('/patient', [LabResultsController::class, 'labResults'])->name('labResults');
        Route::get('/{id}', [LabResultsController::class, 'show'])->name('show');

        // Panel Results Controller routes (Innoquest)
        Route::post('/panel', [PanelResultsController::class, 'panelResults'])->name('panelResults');
    });
});

// General API endpoints - 1000/minute
Route::middleware(['api.auth', 'throttle:api'])->group(function () {
    Route::prefix('import')->controller(ImportController::class)->group(function () {
        Route::get('/innoquestCodeMapping', 'innoquestCodeMapping')->name('innoquestCodeMapping');
        Route::get('/json', 'json')->name('json');
        // Route::get('/innoquestPanelSequence', 'innoquestPanelSequence')->name('innoquestPanelSequence');
        // Route::get('/labNumber', 'labNumber')->name('labNumber');
    });

    Route::prefix('pdf')->controller(PDFController::class)->group(function () {
        Route::post('/export', 'export')->name('export');
    });
    Route::prefix('comment')->controller(PanelCommentController::class)->group(function () {
        Route::get('/update', 'update')->name('update');
    });

    Route::prefix('export')->controller(ExportController::class)->group(function () {
        Route::get('/age', 'exportAge')->name('export.age');
        Route::get('/bt/age', 'exportBtAge')->name('export.bt');
    });

    Route::prefix('export/dynamic')->controller(DynamicExportController::class)->group(function () {
        Route::get('/options',        'options')->name('export.dynamic.options');
        Route::post('/count',         'count')->name('export.dynamic.count');
        Route::post('/',              'export')->name('export.dynamic.export');
        Route::post('/queue',         'queue')->name('export.dynamic.queue');
        Route::get('/status/{uuid}',  'status')->name('export.dynamic.status');
    });

    Route::prefix('odb')->controller(BloodTestController::class)->group(function () {
        Route::post('/getReportId', 'getReportId')->name('odb.getReportId');
        Route::post('/getReviewById', 'getReviewById')->name('odb.getReviewById');
        Route::post('/regenerateReviewById', 'regenerateReviewById')->name('odb.regenerateReviewById');
        Route::post('/updateReportId/{reportId}', 'updateReportId')->name('odb.updateReportId');
        Route::post('/checkVitals', 'checkVitals')->name('checkVitals');
        Route::post('/searchReportId', 'searchReportId')->name('searchReportId');
        Route::post('/searchLabNo', 'searchLabNo')->name('searchLabNo');
        Route::post('/updateLabNo', 'updateLabNo')->name('updateLabNo');

        Route::post('/migrate', 'migrate')->name('odb.migrate');
        Route::post('/migrate-test', 'migrateTest')->name('odb.migrate.test');
        Route::get('/migration-status/{uuid}', 'migrationStatus')->name('odb.migration.status');
    });

    Route::prefix('nexus')->controller(IntegrationController::class)->group(function () {
        Route::post('/icno', 'getResultByICNo')->name('nexus.result-by-icno');
        Route::post('/id', 'getResultById')->name('nexus.result-by-id');
    });

    Route::prefix('fixes')->controller(HotFixController::class)->group(function () {
        Route::post('/normalize-refid', 'normalizeRefId')->name('fixes.normalizeRefId');
    });

    Route::prefix('special-test')->controller(SpecialTestController::class)->group(function () {
        Route::get('/', 'index')->name('special-test.index');
    });

});

// Consult-call auth routes -- NO middleware (entry point for ODB frontend)
Route::prefix('consult-call/auth')->controller(ConsultCallAuthController::class)->group(function () {
    Route::post('/', 'auth');
    Route::post('/verify', 'verifyToken');
});

// Consult-call protected routes -- custom JWT auth (separate from api.auth)
Route::middleware(['consult-call.auth', 'throttle:api'])->group(function () {
    // Static-path routes must be registered before wildcard {id} routes to avoid capture
    Route::prefix('consult-call')->controller(ClinicalConditionController::class)->group(function () {
        Route::get('/clinical-conditions', 'index')->name('consult-call.clinical-conditions.index');
        Route::put('/clinical-conditions/{id}', 'update')->name('consult-call.clinical-conditions.update');
        Route::patch('/clinical-conditions/{id}/toggle', 'toggle')->name('consult-call.clinical-conditions.toggle');
    });

    Route::prefix('consult-call')->controller(ConsultCallController::class)->group(function () {
        Route::get('/', 'index');
        Route::get('/summary', 'summary');
        Route::post('/', 'store');
        Route::get('/{id}', 'show')->whereNumber('id');
        Route::put('/{id}', 'update')->whereNumber('id');
        Route::delete('/{id}', 'destroy')->whereNumber('id');
        Route::get('/{id}/pdf', 'exportPdf')->whereNumber('id');
        Route::post('/{id}/details', 'storeDetails')->whereNumber('id');
        Route::put('/{id}/details/{detailId}', 'updateDetails')->whereNumber('id');
        Route::delete('/{id}/details/{detailId}', 'destroyDetails')->whereNumber('id');
        Route::post('/{id}/follow-up', 'storeFollowUp')->whereNumber('id');
        Route::put('/{id}/follow-up/{followUpId}', 'updateFollowUp')->whereNumber('id');
        Route::delete('/{id}/follow-up/{followUpId}', 'destroyFollowUp')->whereNumber('id');
    });

    Route::prefix('consult-call')->controller(ConsultCallFollowUpController::class)->group(function () {
        Route::patch('/{id}/follow-up/{followUpId}/link-referral', 'linkReferral')->whereNumber('id');
        Route::patch('/{id}/link-referral-by-call', 'linkReferralByCall')->whereNumber('id');
    });

    Route::prefix('consult-call/statuses')->controller(StatusLibraryController::class)->group(function () {
        Route::get('enrollment-types', 'enrollmentTypes')->name('consult-call.statuses.enrollment-types');
        Route::get('consent-call-statuses', 'consentCallStatuses')->name('consult-call.statuses.consent-call-statuses');
        Route::get('scheduled-statuses', 'scheduledStatuses')->name('consult-call.statuses.scheduled-statuses');
        Route::get('modes-of-consultation', 'modesOfConsultation')->name('consult-call.statuses.modes-of-consultation');
        Route::get('actions', 'actions')->name('consult-call.statuses.actions');
        Route::get('consult-statuses', 'consultStatuses')->name('consult-call.statuses.consult-statuses');
        Route::get('process-statuses', 'processStatuses')->name('consult-call.statuses.process-statuses');
        Route::get('follow-up-types', 'followUpTypes')->name('consult-call.statuses.follow-up-types');
        Route::get('next-follow-ups', 'nextFollowUps')->name('consult-call.statuses.next-follow-ups');
Route::get('follow-up-reminders', 'followUpReminders')->name('consult-call.statuses.follow-up-reminders');
    });
});
