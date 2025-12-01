<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AIReview;
use App\Models\DoctorReview;
use App\Models\Patient;
use App\Models\TestResult;
use App\Models\ResultLibrary;
use App\Services\AIReviewService;
use App\Services\ApiTokenService;
use App\Services\MyHealthService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Http;

class DoctorReviewController extends Controller
{
    protected $myHealthService;
    protected $apiTokenService;
    protected $aiReviewService;

    public function __construct(
        MyHealthService $myHealthService,
        ApiTokenService $apiTokenService,
        AIReviewService $aiReviewService
    ) {
        $this->myHealthService = $myHealthService;
        $this->apiTokenService = $apiTokenService;
        $this->aiReviewService = $aiReviewService;
    }

    /**
     * Get appropriate log channel (job if called from job context, default otherwise)
     */
    private function getLogChannel()
    {
        // If we're in a queue job context, use job channel
        if (app()->bound('queue.job')) {
            return 'job';
        }
        // Default to standard logging
        return config('logging.default');
    }

    /**
     * Store generated AI review to DoctorReview
     */
    public function store($id, $testResultData, $result)
    {
        DoctorReview::firstOrCreate(
            [
                'test_result_id' => $id,
            ],
            [
                'compiled_results' => $testResultData,
                'review' => $result,
                'is_sync' => false
            ]
        );
    }

    /**
     * Compile raw data from Test Result, Test Result Item and MyHealth
     * Send compiled data in JSON format to API AI
     *
     * REFACTORED: Now uses AIReviewService to eliminate code duplication
     * Old implementation kept below as backup (search for "OLD CODE - BACKUP")
     */
    public function processResult($testResultId)
    {
        // Increase execution time for external API calls
        ini_set('max_execution_time', 300); // 5 minutes
        $processingStartTime = now();

        try {
            // Process using AIReviewService (new implementation)
            $result = $this->aiReviewService->processSingle($testResultId);

            $processingTime = now()->diffInSeconds($processingStartTime);

            Log::channel($this->getLogChannel())->info('DoctorReviewController processResult completed', [
                'test_result_id' => $testResultId,
                'success' => $result->isSuccessful(),
                'processing_time' => $processingTime . 's'
            ]);
        } catch (Exception $e) {
            Log::channel($this->getLogChannel())->error('Critical error in processResult method', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'test_result_id' => $testResultId
            ]);
        }
    }
}