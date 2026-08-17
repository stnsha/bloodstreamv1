<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\Catalog\DoctorController;
use App\Http\Controllers\API\Catalog\MasterPanelCommentController;
use App\Http\Controllers\API\Catalog\MasterPanelController;
use App\Http\Controllers\API\Catalog\MasterPanelItemController;
use App\Http\Controllers\API\Catalog\PanelCategoryController;
use App\Http\Controllers\API\Catalog\PanelCommentController as CatalogPanelCommentController;
use App\Http\Controllers\API\Catalog\PanelController;
use App\Http\Controllers\API\Catalog\PanelInterpretationController;
use App\Http\Controllers\API\Catalog\PanelItemController;
use App\Http\Controllers\API\Catalog\PanelPanelItemController;
use App\Http\Controllers\API\Catalog\PanelPanelProfileController;
use App\Http\Controllers\API\Catalog\PanelProfileController;
use App\Http\Controllers\API\Catalog\PatientController;
use App\Http\Controllers\API\Catalog\ReferenceRangeController;
use App\Http\Controllers\API\Fixes\HotFixController;
use App\Http\Controllers\API\Nexus\IntegrationController;
use App\Http\Controllers\API\General\LabResultsController;
use App\Http\Controllers\API\General\TestResultController;
use App\Http\Controllers\API\General\TestResultItemController;
use App\Http\Controllers\API\General\TestResultSpecialTestController;
use App\Http\Controllers\API\Innoquest\PanelResultsController;
use App\Http\Controllers\API\ODB\BloodTestController;
use App\Http\Controllers\API\ODB\IncompleteTestResultsController;
use App\Http\Controllers\API\Innoquest\PDFController;
use App\Http\Controllers\API\ConsultCall\AddOnController;
use App\Http\Controllers\API\ConsultCall\ClinicalConditionController;
use App\Http\Controllers\API\ConsultCall\ConsultCallAuthController;
use App\Http\Controllers\API\ConsultCall\ConsultCallController;
use App\Http\Controllers\API\ConsultCall\ConsultCallFollowUpController;
use App\Http\Controllers\API\ConsultCall\StatusLibraryController;
use App\Http\Controllers\API\Lab\LabController;
use App\Http\Controllers\API\MyHealth\MyHealthController;
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
        Route::post('/updateReportId/{reportId}', 'updateReportId')->name('odb.updateReportId');
        Route::post('/checkVitals', 'checkVitals')->name('checkVitals');
        Route::post('/checkConsultCall', 'checkConsultCall')->name('odb.checkConsultCall');
        Route::post('/searchReportId', 'searchReportId')->name('searchReportId');
        Route::post('/searchLabNo', 'searchLabNo')->name('searchLabNo');
        Route::post('/getLabNoReport', 'getLabNoReport')->name('getLabNoReport');
        Route::post('/markLabNoCompleted', 'markLabNoCompleted')->name('markLabNoCompleted');
        Route::post('/bulkMarkLabNoCompleted', 'bulkMarkLabNoCompleted')->name('bulkMarkLabNoCompleted');
        Route::post('/revertIncompleteTestResult', 'revertIncompleteTestResult')->name('odb.revertIncompleteTestResult');
        Route::post('/updateLabNo', 'updateLabNo')->name('updateLabNo');

        Route::post('/migrate', 'migrate')->name('odb.migrate');
        Route::post('/migrate-test', 'migrateTest')->name('odb.migrate.test');
        Route::get('/migration-status/{uuid}', 'migrationStatus')->name('odb.migration.status');
    });

    Route::prefix('odb')->controller(IncompleteTestResultsController::class)->group(function () {
        Route::get('/incompleteTestResults', 'index')->name('odb.incompleteTestResults');
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

    Route::prefix('test-results')->controller(TestResultController::class)->group(function () {
        Route::get('/', 'index')->name('testResults.index');
        Route::post('/search', 'search')->name('testResults.search');
    });

    Route::prefix('test-result-items')->controller(TestResultItemController::class)->group(function () {
        Route::get('/test-result/{testResultId}', 'byTestResult')->whereNumber('testResultId')->name('testResultItems.byTestResult');
        Route::get('/panel-panel-item/{panelPanelItemId}', 'byPanelPanelItem')->whereNumber('panelPanelItemId')->name('testResultItems.byPanelPanelItem');
        Route::get('/', 'all')->name('testResultItems.all');
    });

    Route::prefix('test-result-special-tests')->controller(TestResultSpecialTestController::class)->group(function () {
        Route::get('/panel-interpretation/{panelInterpretationId}', 'byPanelInterpretation')->whereNumber('panelInterpretationId')->name('testResultSpecialTests.byPanelInterpretation');
        Route::get('/', 'all')->name('testResultSpecialTests.all');
    });

    Route::prefix('patients')->controller(PatientController::class)->group(function () {
        Route::get('/gender/{gender}', 'byGender')->name('patients.byGender');
        Route::get('/age/{age}', 'byAge')->name('patients.byAge');
        Route::get('/{id}', 'show')->whereNumber('id')->name('patients.show');
        Route::get('/', 'index')->name('patients.index');
    });

    Route::prefix('doctors')->controller(DoctorController::class)->group(function () {
        Route::get('/lab/{labId}', 'byLab')->whereNumber('labId')->name('doctors.byLab');
        Route::get('/{id}', 'show')->whereNumber('id')->name('doctors.show');
        Route::get('/', 'index')->name('doctors.index');
    });

    Route::prefix('master-panels')->controller(MasterPanelController::class)->group(function () {
        Route::get('/{id}', 'show')->whereNumber('id')->name('masterPanels.show');
        Route::get('/', 'index')->name('masterPanels.index');
    });

    Route::prefix('master-panel-comments')->controller(MasterPanelCommentController::class)->group(function () {
        Route::get('/{id}', 'show')->whereNumber('id')->name('masterPanelComments.show');
        Route::get('/', 'index')->name('masterPanelComments.index');
    });

    Route::prefix('master-panel-items')->controller(MasterPanelItemController::class)->group(function () {
        Route::get('/{id}', 'show')->whereNumber('id')->name('masterPanelItems.show');
        Route::get('/', 'index')->name('masterPanelItems.index');
    });

    Route::prefix('panels')->controller(PanelController::class)->group(function () {
        Route::get('/master-panel/{masterPanelId}', 'byMasterPanel')->whereNumber('masterPanelId')->name('panels.byMasterPanel');
        Route::get('/panel-category/{panelCategoryId}', 'byPanelCategory')->whereNumber('panelCategoryId')->name('panels.byPanelCategory');
        Route::get('/code/{code}', 'byCode')->name('panels.byCode');
        Route::get('/{id}', 'show')->whereNumber('id')->name('panels.show');
        Route::get('/', 'index')->name('panels.index');
    });

    Route::prefix('panel-categories')->controller(PanelCategoryController::class)->group(function () {
        Route::get('/lab/{labId}', 'byLab')->whereNumber('labId')->name('panelCategories.byLab');
        Route::get('/{id}', 'show')->whereNumber('id')->name('panelCategories.show');
        Route::get('/', 'index')->name('panelCategories.index');
    });

    Route::prefix('panel-comments')->controller(CatalogPanelCommentController::class)->group(function () {
        Route::get('/panel/{panelId}', 'byPanel')->whereNumber('panelId')->name('panelComments.byPanel');
        Route::get('/master-panel-comment/{masterPanelCommentId}', 'byMasterPanelComment')->whereNumber('masterPanelCommentId')->name('panelComments.byMasterPanelComment');
        Route::get('/{id}', 'show')->whereNumber('id')->name('panelComments.show');
        Route::get('/', 'index')->name('panelComments.index');
    });

    Route::prefix('panel-interpretations')->controller(PanelInterpretationController::class)->group(function () {
        Route::get('/panel-panel-item/{panelPanelItemId}', 'byPanelPanelItem')->whereNumber('panelPanelItemId')->name('panelInterpretations.byPanelPanelItem');
        Route::get('/{id}', 'show')->whereNumber('id')->name('panelInterpretations.show');
        Route::get('/', 'index')->name('panelInterpretations.index');
    });

    Route::prefix('panel-items')->controller(PanelItemController::class)->group(function () {
        Route::get('/master-panel-item/{masterPanelItemId}', 'byMasterPanelItem')->whereNumber('masterPanelItemId')->name('panelItems.byMasterPanelItem');
        Route::get('/lab/{labId}', 'byLab')->whereNumber('labId')->name('panelItems.byLab');
        Route::get('/{id}', 'show')->whereNumber('id')->name('panelItems.show');
        Route::get('/', 'index')->name('panelItems.index');
    });

    Route::prefix('panel-panel-items')->controller(PanelPanelItemController::class)->group(function () {
        Route::get('/panel/{panelId}', 'byPanel')->whereNumber('panelId')->name('panelPanelItems.byPanel');
        Route::get('/panel-item/{panelItemId}', 'byPanelItem')->whereNumber('panelItemId')->name('panelPanelItems.byPanelItem');
        Route::get('/{id}', 'show')->whereNumber('id')->name('panelPanelItems.show');
        Route::get('/', 'index')->name('panelPanelItems.index');
    });

    Route::prefix('panel-panel-profiles')->controller(PanelPanelProfileController::class)->group(function () {
        Route::get('/panel-profile/{panelProfileId}', 'byPanelProfile')->whereNumber('panelProfileId')->name('panelPanelProfiles.byPanelProfile');
        Route::get('/panel/{panelId}', 'byPanel')->whereNumber('panelId')->name('panelPanelProfiles.byPanel');
        Route::get('/{id}', 'show')->whereNumber('id')->name('panelPanelProfiles.show');
        Route::get('/', 'index')->name('panelPanelProfiles.index');
    });

    Route::prefix('panel-profiles')->controller(PanelProfileController::class)->group(function () {
        Route::get('/lab/{labId}', 'byLab')->whereNumber('labId')->name('panelProfiles.byLab');
        Route::get('/{id}', 'show')->whereNumber('id')->name('panelProfiles.show');
        Route::get('/', 'index')->name('panelProfiles.index');
    });

    Route::prefix('reference-ranges')->controller(ReferenceRangeController::class)->group(function () {
        Route::get('/panel-panel-item/{panelPanelItemId}', 'byPanelPanelItem')->whereNumber('panelPanelItemId')->name('referenceRanges.byPanelPanelItem');
        Route::get('/{id}', 'show')->whereNumber('id')->name('referenceRanges.show');
        Route::get('/', 'index')->name('referenceRanges.index');
    });

    Route::prefix('myhealth')->controller(MyHealthController::class)->group(function () {
        Route::get('/check-record/{ic}', 'checkRecordByIc')->name('myhealth.checkRecordByIc');
    });

    Route::prefix('lab')->controller(LabController::class)->group(function () {
        Route::get('/', 'index')->name('lab.index');
        Route::post('/', 'store')->name('lab.store');
        Route::get('/{id}', 'show')->whereNumber('id')->name('lab.show');
        Route::put('/{id}', 'update')->whereNumber('id')->name('lab.update');
        Route::delete('/{id}', 'destroy')->whereNumber('id')->name('lab.destroy');
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

    Route::prefix('consult-call')->controller(AddOnController::class)->group(function () {
        // Static-path routes must be registered before wildcard {id} routes to avoid capture
        Route::get('/add-ons/all', 'all')->name('consult-call.add-ons.all');
        Route::get('/add-ons', 'index')->name('consult-call.add-ons.index');
        Route::post('/add-ons', 'store')->name('consult-call.add-ons.store');
        Route::put('/add-ons/{id}', 'update')->name('consult-call.add-ons.update');
        Route::patch('/add-ons/{id}/toggle', 'toggle')->name('consult-call.add-ons.toggle');
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
