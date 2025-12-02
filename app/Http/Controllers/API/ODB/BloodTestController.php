<?php

namespace App\Http\Controllers\API\ODB;

use App\Http\Controllers\Controller;
use App\Http\Requests\ODB\MigrateRequest;
use App\Http\Requests\ODB\ODBRequest;
use App\Jobs\ProcessMigrationBatch;
use App\Models\AIReview;
use App\Models\MigrationBatch;
use App\Models\MigrationBatchItem;
use App\Models\Patient;
use App\Models\ResultLibrary;
use App\Models\TestResult;
use App\Services\AIReviewService;
use App\Services\ApiTokenService;
use App\Services\MyHealthService;
use App\Services\ODB\MigrationService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class BloodTestController extends Controller
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

    private function getLogChannel()
    {
        return 'odb-log';
    }

    /**
     * Sync Test Result ID as Report ID to ODB
     */
    public function getReportId(ODBRequest $request)
    {
        $validated = $request->all();
        $totalItems = count($validated);
        $successCount = 0;
        $notFoundCount = 0;
        $processingStartTime = now();

        Log::channel($this->getLogChannel())->info('getReportId: Processing started', [
            'total_items' => $totalItems,
            'timestamp' => $processingStartTime
        ]);

        try {
            $results = DB::transaction(function () use ($validated, &$successCount, &$notFoundCount) {
                $results = [];

                foreach ($validated as $index => $item) {
                    $icno = $item['icno'];
                    $refid = $item['refid'] ?? null;
                    $month = $item['month'] ?? null;
                    $year = $item['year'] ?? null;

                    $year  = $year  ?: date('Y');
                    $month = $month ?: date('m');
                    $itemNumber = $index + 1;

                    Log::channel($this->getLogChannel())->info('getReportId: Processing item', [
                        'item_number' => $itemNumber,
                        'icno' => $icno,
                        'refid' => $refid
                    ]);

                    $testResult = null;

                    // Step 1: If refid provided, try searching by BOTH IC number AND refid
                    if ($refid) {
                        Log::channel($this->getLogChannel())->debug('getReportId: Searching by IC AND refid', [
                            'icno' => $icno,
                            'refid' => $refid
                        ]);

                        $testResult = TestResult::whereHas('patient', function ($p) use ($icno) {
                            $p->where('icno', $icno);
                        })
                            ->where('ref_id', $refid)
                            ->where('is_completed', true)
                            ->whereNotNull('collected_date')
                            ->whereYear('collected_date', $year)
                            ->whereMonth('collected_date', $month)
                            ->latest()->first();

                        if ($testResult) {
                            Log::channel($this->getLogChannel())->info('getReportId: Test result found by IC AND refid', [
                                'icno' => $icno,
                                'refid' => $refid,
                                'test_result_id' => $testResult->id,
                                'is_completed' => $testResult->is_completed,
                                'is_completed_raw' => $testResult->getRawOriginal('is_completed')
                            ]);
                        }
                    }

                    // Step 2: Search by IC number only
                    if (!$testResult) {
                        Log::channel($this->getLogChannel())->debug('getReportId: Searching by IC number', [
                            'icno' => $icno
                        ]);

                        $query = TestResult::whereHas('patient', function ($p) use ($icno) {
                            $p->where('icno', $icno);
                        });

                        // Only require NULL ref_id if user provided a refid
                        if ($refid) {
                            $query->whereNull('ref_id');
                        }

                        $testResult = $query
                            ->where('is_completed', true)
                            ->whereNotNull('collected_date')
                            ->whereYear('collected_date', $year)
                            ->whereMonth('collected_date', $month)
                            ->latest()->first();

                        if ($testResult) {
                            $logMessage = $refid
                                ? 'getReportId: Test result found by IC with NULL ref_id'
                                : 'getReportId: Test result found by IC number';

                            Log::channel($this->getLogChannel())->info($logMessage, [
                                'icno' => $icno,
                                'test_result_id' => $testResult->id,
                                'is_completed' => $testResult->is_completed,
                                'is_completed_raw' => $testResult->getRawOriginal('is_completed'),
                                'database_ref_id' => $testResult->ref_id
                            ]);

                            // Update ref_id if it's null and we have a refid to set
                            if ($refid && is_null($testResult->ref_id)) {
                                $testResult->ref_id = $refid;
                                $testResult->save();

                                Log::channel($this->getLogChannel())->info('getReportId: Updated ref_id from null', [
                                    'test_result_id' => $testResult->id,
                                    'new_ref_id' => $refid
                                ]);
                            }
                        }
                    }

                    // Step 3: Fallback to search by refid if provided
                    if (!$testResult && $refid) {
                        Log::channel($this->getLogChannel())->debug('getReportId: IC search failed, falling back to refid search', [
                            'icno' => $icno,
                            'refid' => $refid
                        ]);

                        $testResult = TestResult::where('ref_id', $refid)
                            ->where('is_completed', true)
                            ->whereNotNull('collected_date')
                            ->whereYear('collected_date', $year)
                            ->whereMonth('collected_date', $month)
                            ->latest()->first();

                        if ($testResult) {
                            // Verify IC mismatch - only return if IC is different
                            $foundIcno = $testResult->patient->icno ?? null;

                            if ($foundIcno !== $icno) {
                                Log::channel($this->getLogChannel())->info('getReportId: Test result found by refid with different IC', [
                                    'refid' => $refid,
                                    'provided_icno' => $icno,
                                    'found_icno' => $foundIcno,
                                    'test_result_id' => $testResult->id,
                                    'is_completed' => $testResult->is_completed,
                                    'is_completed_raw' => $testResult->getRawOriginal('is_completed')
                                ]);
                            } else {
                                // IC matches - reject to avoid returning mismatched record
                                Log::channel($this->getLogChannel())->warning('getReportId: Found by refid but IC matches - rejecting', [
                                    'refid' => $refid,
                                    'icno' => $icno,
                                    'test_result_id' => $testResult->id,
                                    'database_ref_id' => $testResult->ref_id
                                ]);
                                $testResult = null;
                            }
                        }
                    }

                    // Only add to results if test result found
                    if ($testResult) {

                        $resultData = [
                            'icno' => $icno,
                            'refid' => $refid,
                            'report_id' => $testResult->id
                        ];

                        $results[] = $resultData;
                        $successCount++;

                        Log::channel($this->getLogChannel())->info('getReportId: Item processed successfully', [
                            'item_number' => $itemNumber,
                            'icno' => $icno,
                            'refid' => $refid,
                            'report_id' => $testResult->id
                        ]);
                    } else {
                        $notFoundCount++;

                        Log::channel($this->getLogChannel())->warning('getReportId: Test result not found', [
                            'item_number' => $itemNumber,
                            'icno' => $icno,
                            'refid' => $refid
                        ]);
                    }
                }

                return $results;
            });

            $processingTime = now()->diffInSeconds($processingStartTime);

            Log::channel($this->getLogChannel())->info('getReportId: Processing completed', [
                'total_items' => $totalItems,
                'success_count' => $successCount,
                'not_found_count' => $notFoundCount,
                'processing_time_seconds' => $processingTime
            ]);

            return response()->json($results);
        } catch (Throwable $e) {
            Log::channel($this->getLogChannel())->error('getReportId: Critical error occurred', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'total_items' => $totalItems,
                'success_count' => $successCount,
                'not_found_count' => $notFoundCount
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing the request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Retrieve IC and refID from ODB to check if AI review exist
     * If not exist, generate 
     */
    public function review(ODBRequest $request)
    {
        // Increase execution time for external API calls
        ini_set('max_execution_time', 300); // 5 minutes
        $validated = $request->all();
        $processingStartTime = now();

        Log::channel($this->getLogChannel())->info('AI Review process started', [
            'total_items' => count($validated),
            'timestamp' => $processingStartTime
        ]);

        try {
            // Process using AIReviewService (new implementation - handles bulk processing)
            $results = $this->aiReviewService->processBulk($validated);

            $successfulResults = array_filter($results, fn($r) => $r->isSuccessful());
            $failedResults = array_filter($results, fn($r) => $r->isFailed());

            $processingTime = now()->diffInSeconds($processingStartTime);

            Log::channel($this->getLogChannel())->info('AI Review process completed', [
                'total_items' => count($validated),
                'processed_results' => count($results),
                'successful_results' => count($successfulResults),
                'failed_results' => count($failedResults),
                'processing_time' => $processingTime . 's'
            ]);

            if (count($successfulResults) > 0) {
                return response()->json(
                    array_map(fn($r) => $r->toArray(), $successfulResults)
                );
            }

            return response()->json([
                'success' => false,
                'message' => 'No test results could be processed successfully',
                'failed_results' => array_map(fn($r) => $r->toArray(), $failedResults),
            ], 404);
        } catch (Exception $e) {
            Log::channel($this->getLogChannel())->error('Critical error in review method', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'total_items' => count($validated)
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Critical error occurred during processing',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get review by ID - Return existing AI review only
     * Does not create new review if not exists
     */
    public function getReviewById(ODBRequest $request)
    {
        $validated = $request->all();
        $processingStartTime = now();

        // Extract single item from request
        $item = $validated[0] ?? null;

        if (!$item) {
            Log::channel($this->getLogChannel())->warning('getReviewById: No item provided in request');
            return response()->json([
                'ai_response' => null,
                'report_id' => null,
                'ref_id' => null,
                'status' => 'Completed'
            ]);
        }

        $icno = $item['icno'];
        $refid = $item['refid'] ?? null;
        $month = $item['month'] ?? null;
        $year  = $item['year'] ?? null;

        $year  = $year  ?: date('Y');
        $month = $month ?: date('m');

        Log::channel($this->getLogChannel())->info('getReviewById: Processing started', [
            'icno' => $icno,
            'refid' => $refid,
            'timestamp' => $processingStartTime
        ]);

        try {
            $testResult = null;

            // Step 1: If refid provided, try searching by BOTH IC number AND refid
            if ($refid) {
                Log::channel($this->getLogChannel())->debug('getReviewById: Searching by IC AND refid', [
                    'icno' => $icno,
                    'refid' => $refid
                ]);

                $testResult = TestResult::whereHas('patient', function ($p) use ($icno) {
                    $p->where('icno', $icno);
                })
                    ->where('ref_id', $refid)
                    ->where('is_completed', true)
                    ->whereNotNull('collected_date')
                    ->whereYear('collected_date', $year)
                    ->whereMonth('collected_date', $month)
                    ->latest()->first();

                if ($testResult) {
                    Log::channel($this->getLogChannel())->info('getReviewById: Test result found by IC AND refid', [
                        'icno' => $icno,
                        'refid' => $refid,
                        'test_result_id' => $testResult->id,
                        'is_completed' => $testResult->is_completed,
                        'is_completed_raw' => $testResult->getRawOriginal('is_completed'),
                        'is_reviewed' => $testResult->is_reviewed,
                        'is_reviewed_raw' => $testResult->getRawOriginal('is_reviewed')
                    ]);
                }
            }

            // Step 2: Search by IC number only
            if (!$testResult) {
                Log::channel($this->getLogChannel())->debug('getReviewById: Searching by IC number', [
                    'icno' => $icno
                ]);

                $query = TestResult::whereHas('patient', function ($p) use ($icno) {
                    $p->where('icno', $icno);
                });

                // Only require NULL ref_id if user provided a refid
                if ($refid) {
                    $query->whereNull('ref_id');
                }

                $testResult = $query
                    ->where('is_completed', true)
                    ->whereNotNull('collected_date')
                    ->whereYear('collected_date', $year)
                    ->whereMonth('collected_date', $month)
                    ->latest()->first();

                if ($testResult) {
                    $logMessage = $refid
                        ? 'getReviewById: Test result found by IC with NULL ref_id'
                        : 'getReviewById: Test result found by IC number';

                    Log::channel($this->getLogChannel())->info($logMessage, [
                        'icno' => $icno,
                        'test_result_id' => $testResult->id,
                        'is_completed' => $testResult->is_completed,
                        'is_completed_raw' => $testResult->getRawOriginal('is_completed'),
                        'is_reviewed' => $testResult->is_reviewed,
                        'is_reviewed_raw' => $testResult->getRawOriginal('is_reviewed'),
                        'database_ref_id' => $testResult->ref_id
                    ]);

                    // Update ref_id if it's null and we have a refid to set
                    if ($refid && is_null($testResult->ref_id)) {
                        $testResult->ref_id = $refid;
                        $testResult->save();

                        Log::channel($this->getLogChannel())->info('getReviewById: Updated ref_id from null', [
                            'test_result_id' => $testResult->id,
                            'new_ref_id' => $refid
                        ]);
                    }
                }
            }

            // Step 3: Fallback to search by refid if provided
            if (!$testResult && $refid) {
                Log::channel($this->getLogChannel())->debug('getReviewById: IC search failed, falling back to refid search', [
                    'icno' => $icno,
                    'refid' => $refid
                ]);

                $testResult = TestResult::where('ref_id', $refid)
                    ->where('is_completed', true)
                    ->whereNotNull('collected_date')
                    ->whereYear('collected_date', $year)
                    ->whereMonth('collected_date', $month)
                    ->latest()->first();

                if ($testResult) {
                    // Verify IC mismatch - only return if IC is different
                    $foundIcno = $testResult->patient->icno ?? null;

                    if ($foundIcno !== $icno) {
                        Log::channel($this->getLogChannel())->info('getReviewById: Test result found by refid with different IC', [
                            'refid' => $refid,
                            'provided_icno' => $icno,
                            'found_icno' => $foundIcno,
                            'test_result_id' => $testResult->id,
                            'is_completed' => $testResult->is_completed,
                            'is_completed_raw' => $testResult->getRawOriginal('is_completed'),
                            'is_reviewed' => $testResult->is_reviewed,
                            'is_reviewed_raw' => $testResult->getRawOriginal('is_reviewed')
                        ]);
                    } else {
                        // IC matches - reject to avoid returning mismatched record
                        Log::channel($this->getLogChannel())->warning('getReviewById: Found by refid but IC matches - rejecting', [
                            'refid' => $refid,
                            'icno' => $icno,
                            'test_result_id' => $testResult->id,
                            'database_ref_id' => $testResult->ref_id
                        ]);
                        $testResult = null;
                    }
                }
            }


            //Step 4: Check with manual sync for unmatch date
            if ($month != date('m')) {
                $testResult = TestResult::whereHas('patient', function ($p) use ($icno) {
                    $p->where('icno', $icno);
                })
                    ->where('ref_id', $refid)
                    ->where('is_completed', true)
                    ->whereNotNull('manual_sync_date')
                    ->latest()->first();

                if ($testResult) {
                    Log::channel($this->getLogChannel())->info('getReviewById: Test result found by manual_sync_date', [
                        'icno' => $icno,
                        'refid' => $refid,
                        'test_result_id' => $testResult->id,
                        'is_completed' => $testResult->is_completed,
                        'is_completed_raw' => $testResult->getRawOriginal('is_completed'),
                        'is_reviewed' => $testResult->is_reviewed,
                        'is_reviewed_raw' => $testResult->getRawOriginal('is_reviewed')
                    ]);
                }
            }

            if (!$testResult) {
                // Return status if test result not found
                Log::channel($this->getLogChannel())->warning('getReviewById: Test result not found', [
                    'icno' => $icno,
                    'refid' => $refid
                ]);

                return response()->json([
                    'ai_response' => null,
                    'report_id' => null,
                    'ref_id' => $refid,
                    'status' => 'Record not found'
                ]);
            }

            // Update ref_id if request has refid but DB has null
            if ($refid && $testResult->ref_id === null) {
                Log::channel($this->getLogChannel())->debug('getReviewById: Updating null ref_id in database', [
                    'test_result_id' => $testResult->id,
                    'old_ref_id' => null,
                    'new_ref_id' => $refid
                ]);

                $testResult->ref_id = $refid;
                $testResult->save();

                Log::channel($this->getLogChannel())->info('getReviewById: ref_id updated successfully', [
                    'test_result_id' => $testResult->id,
                    'ref_id' => $refid
                ]);
            }

            // Check if test result is completed but not reviewed
            if ($testResult->is_completed && !$testResult->is_reviewed) {
                $processingTime = now()->diffInSeconds($processingStartTime);

                Log::channel($this->getLogChannel())->info('getReviewById: Test result completed but not reviewed', [
                    'test_result_id' => $testResult->id,
                    'is_completed' => $testResult->is_completed,
                    'is_reviewed' => $testResult->is_reviewed,
                    'processing_time_seconds' => $processingTime
                ]);

                return response()->json([
                    'ai_response' => null,
                    'report_id' => $testResult->id,
                    'ref_id' => $testResult->ref_id,
                    'status' => 'Completed but no AI Report to be generated'
                ]);
            }

            // Check if AIReview exists for this test result using relationship
            $aiReview = $testResult->aiReview;

            if (!$aiReview) {
                Log::channel($this->getLogChannel())->warning('getReviewById: AIReview not found for reviewed test result', [
                    'test_result_id' => $testResult->id
                ]);

                return response()->json([
                    'ai_response' => null,
                    'report_id' => $testResult->id,
                    'ref_id' => $testResult->ref_id,
                    'status' => 'Completed but no AI Report to be generated'
                ]);
            }

            Log::channel($this->getLogChannel())->info('getReviewById: AIReview found', [
                'test_result_id' => $testResult->id,
                'ai_review_id' => $aiReview->id
            ]);

            $processingTime = now()->diffInSeconds($processingStartTime);

            // Determine status based on is_completed value
            $status = $testResult->is_completed ? 'Completed' : 'Sync In Progress';

            $responseData = [
                'ai_response' => $aiReview->ai_response,
                'report_id' => $testResult->id,
                'ref_id' => $testResult->ref_id,
                'status' => $status
            ];

            Log::channel($this->getLogChannel())->info('getReviewById: Processing completed', [
                'test_result_id' => $testResult->id,
                'is_completed' => $testResult->is_completed,
                'is_completed_raw' => $testResult->getRawOriginal('is_completed'),
                'is_reviewed' => $testResult->is_reviewed,
                'is_reviewed_raw' => $testResult->getRawOriginal('is_reviewed'),
                'processing_time_seconds' => $processingTime
            ]);

            return response()->json($responseData);
        } catch (Throwable $e) {
            Log::channel($this->getLogChannel())->error('getReviewById: Critical error occurred', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'icno' => $icno,
                'refid' => $refid
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing the request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Regenerate AI review by ID - Delete existing review and generate new one
     * Similar flow to getReviewById but replaces existing AI review
     */
    public function regenerateReviewById(ODBRequest $request)
    {
        // Increase execution time for potential AI generation
        ini_set('max_execution_time', 300); // 5 minutes

        $validated = $request->all();
        $processingStartTime = now();

        // Extract single item from request
        $item = $validated[0] ?? null;

        if (!$item) {
            Log::channel($this->getLogChannel())->warning('regenerateReviewById: No item provided in request');
            return response()->json(null);
        }

        $icno = $item['icno'];
        $refid = $item['refid'] ?? null;
        $month = $item['month'] ?? null;
        $year  = $item['year'] ?? null;

        Log::channel($this->getLogChannel())->debug('regenerateReviewById: Checking month and year received', [
            'month' => $month,
            'year' => $year
        ]);

        $year  = $year  ?: date('Y');
        $month = $month ?: date('m');

        Log::channel($this->getLogChannel())->info('regenerateReviewById: Processing started', [
            'icno' => $icno,
            'refid' => $refid,
            'timestamp' => $processingStartTime
        ]);

        try {
            $testResult = null;

            // Step 1: If refid provided, try searching by BOTH IC number AND refid
            if ($refid) {
                Log::channel($this->getLogChannel())->debug('regenerateReviewById: Searching by IC AND refid', [
                    'icno' => $icno,
                    'refid' => $refid
                ]);

                $testResult = TestResult::whereHas('patient', function ($p) use ($icno) {
                    $p->where('icno', $icno);
                })
                    ->where('ref_id', $refid)
                    ->where('is_completed', true)
                    ->whereNotNull('collected_date')
                    ->whereYear('collected_date', $year)
                    ->whereMonth('collected_date', $month)
                    ->latest()->first();

                if ($testResult) {
                    Log::channel($this->getLogChannel())->info('regenerateReviewById: Test result found by IC AND refid', [
                        'icno' => $icno,
                        'refid' => $refid,
                        'test_result_id' => $testResult->id,
                        'is_completed' => $testResult->is_completed,
                        'is_completed_raw' => $testResult->getRawOriginal('is_completed')
                    ]);
                }
            }

            // Step 2: Search by IC number only
            if (!$testResult) {
                Log::channel($this->getLogChannel())->debug('regenerateReviewById: Searching by IC number', [
                    'icno' => $icno
                ]);

                $query = TestResult::whereHas('patient', function ($p) use ($icno) {
                    $p->where('icno', $icno);
                });

                // Only require NULL ref_id if user provided a refid
                if ($refid) {
                    $query->whereNull('ref_id');
                }

                $testResult = $query
                    ->where('is_completed', true)
                    ->whereNotNull('collected_date')
                    ->whereYear('collected_date', $year)
                    ->whereMonth('collected_date', $month)
                    ->latest()->first();

                if ($testResult) {
                    $logMessage = $refid
                        ? 'regenerateReviewById: Test result found by IC with NULL ref_id'
                        : 'regenerateReviewById: Test result found by IC number';

                    Log::channel($this->getLogChannel())->info($logMessage, [
                        'icno' => $icno,
                        'test_result_id' => $testResult->id,
                        'database_ref_id' => $testResult->ref_id,
                        'is_completed' => $testResult->is_completed,
                        'is_completed_raw' => $testResult->getRawOriginal('is_completed')
                    ]);

                    // Update ref_id if it's null and we have a refid to set
                    if ($refid && is_null($testResult->ref_id)) {
                        $testResult->ref_id = $refid;
                        $testResult->save();

                        Log::channel($this->getLogChannel())->info('regenerateReviewById: Updated ref_id from null', [
                            'test_result_id' => $testResult->id,
                            'new_ref_id' => $refid
                        ]);
                    }
                }
            }

            // Step 3: Fallback to search by refid if provided
            if (!$testResult && $refid) {
                Log::channel($this->getLogChannel())->debug('regenerateReviewById: Searching by refid', [
                    'refid' => $refid
                ]);

                $testResult = TestResult::where('ref_id', $refid)
                    ->where('is_completed', true)
                    ->whereNotNull('collected_date')
                    ->whereYear('collected_date', $year)
                    ->whereMonth('collected_date', $month)
                    ->latest()->first();

                if ($testResult) {
                    // Verify IC mismatch - only return if IC is different
                    $foundIcno = $testResult->patient->icno ?? null;

                    if ($foundIcno !== $icno) {
                        Log::channel($this->getLogChannel())->info('regenerateReviewById: Test result found by refid with different IC', [
                            'refid' => $refid,
                            'provided_icno' => $icno,
                            'found_icno' => $foundIcno,
                            'test_result_id' => $testResult->id,
                            'is_completed' => $testResult->is_completed,
                            'is_completed_raw' => $testResult->getRawOriginal('is_completed')
                        ]);
                    } else {
                        // IC matches - reject to avoid returning mismatched record
                        Log::channel($this->getLogChannel())->warning('regenerateReviewById: Found by refid but IC matches - rejecting', [
                            'refid' => $refid,
                            'icno' => $icno,
                            'test_result_id' => $testResult->id,
                            'database_ref_id' => $testResult->ref_id
                        ]);
                        $testResult = null;
                    }
                }
            }

            // Return null if test result not found or not completed
            if (!$testResult) {
                Log::channel($this->getLogChannel())->warning('regenerateReviewById: Test result not found or not completed', [
                    'icno' => $icno,
                    'refid' => $refid,
                    'month' => $month,
                    'year' => $year
                ]);

                return response()->json(null);
            }

            // Check if AIReview exists for this test result
            $aiReview = $testResult->aiReview;

            if ($aiReview) {
                Log::channel($this->getLogChannel())->info('regenerateReviewById: Existing AIReview found, deleting', [
                    'test_result_id' => $testResult->id,
                    'ai_review_id' => $aiReview->id
                ]);

                // Delete existing AI review
                $aiReview->delete();

                // Update test result is_reviewed to false
                $testResult->is_reviewed = false;
                $testResult->save();

                Log::channel($this->getLogChannel())->info('regenerateReviewById: Existing AIReview deleted and is_reviewed set to false', [
                    'test_result_id' => $testResult->id
                ]);
            }

            // Generate new review synchronously
            Log::channel($this->getLogChannel())->info('regenerateReviewById: Generating new AI review', [
                'test_result_id' => $testResult->id
            ]);

            $result = $this->aiReviewService->processSingle($testResult->id);

            if ($result->isSuccessful()) {
                // Reload the relationship
                $testResult->load('aiReview');
                $aiReview = $testResult->aiReview;

                Log::channel($this->getLogChannel())->info('regenerateReviewById: AIReview regenerated successfully', [
                    'test_result_id' => $testResult->id,
                    'ai_review_id' => $aiReview->id
                ]);
            } else {
                Log::channel($this->getLogChannel())->error('regenerateReviewById: Failed to regenerate AIReview', [
                    'test_result_id' => $testResult->id,
                    'error' => $result->errorMessage
                ]);

                return response()->json(null);
            }

            $processingTime = now()->diffInSeconds($processingStartTime);

            // Determine status based on is_completed value
            $status = $testResult->is_completed ? 'Completed' : 'Sync In Progress';

            $responseData = [
                'ai_response' => $aiReview->ai_response,
                'report_id' => $testResult->id,
                'ref_id' => $testResult->ref_id,
                'status' => $status
            ];

            Log::channel($this->getLogChannel())->info('regenerateReviewById: Processing completed', [
                'test_result_id' => $testResult->id,
                'is_completed' => $testResult->is_completed,
                'is_completed_raw' => $testResult->getRawOriginal('is_completed'),
                'processing_time_seconds' => $processingTime
            ]);

            return response()->json($responseData);
        } catch (Throwable $e) {
            Log::channel($this->getLogChannel())->error('regenerateReviewById: Critical error occurred', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'icno' => $icno,
                'refid' => $refid
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing the request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * New module by Mr Hong
     * Check BP, BMI and report if exist
     */
    public function checkVitals(ODBRequest $request)
    {
        $validated = $request->all();
        $processingStartTime = now();

        Log::channel($this->getLogChannel())->info('checkVitals: Processing started', [
            'total_items' => count($validated),
            'timestamp' => $processingStartTime
        ]);

        $reportId = 0;
        $report = $this->getReportId($request);

        // get decoded JSON data (object)
        $data = $report->getData();

        // validate structure before accessing
        if (
            is_array($data) &&
            isset($data[0]) &&
            isset($data[0]->report_id)
        ) {
            $reportId = $data[0]->report_id;
        } else {
            // fallback or error handling
            $reportId = 0; // or null
        }

        try {
            // Extract ICs from the request
            $ics = array_map(function ($item) {
                return $item['icno'];
            }, $validated);

            Log::channel($this->getLogChannel())->debug('checkVitals: Extracted ICs', [
                'ics' => $ics
            ]);

            // Check if vitals are filled for these ICs
            $results = $this->myHealthService->isFilledVitals($ics);

            // Format response to match request items
            $response = [];
            foreach ($validated as $item) {
                $icno = $item['icno'];
                $refid = $item['refid'] ?? null;

                $response[] = [
                    'icno' => $icno,
                    'refid' => $refid,
                    'is_filled' => $results[$icno] ?? false,
                    'report_id' => $reportId
                ];
            }

            $processingTime = now()->diffInSeconds($processingStartTime);

            Log::channel($this->getLogChannel())->info('checkVitals: Processing completed', [
                'total_items' => count($validated),
                'processing_time_seconds' => $processingTime
            ]);

            return response()->json($response);
        } catch (Throwable $e) {
            Log::channel($this->getLogChannel())->error('checkVitals: Critical error occurred', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'total_items' => count($validated)
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing the request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Manual search existing result
     * Get ALL test results for a given IC number with status and AI review
     */
    public function searchReportId(ODBRequest $request)
    {
        $validated = $request->all();
        $processingStartTime = now();

        // Extract single item from request
        $item = $validated[0] ?? null;

        if (!$item) {
            Log::channel($this->getLogChannel())->warning('searchReportId: No item provided in request');
            return response()->json([]);
        }

        $icno = $item['icno'];

        Log::channel($this->getLogChannel())->info('searchReportId: Processing started', [
            'icno' => $icno,
            'timestamp' => $processingStartTime
        ]);

        try {
            // Query: Get ALL test results for this icno
            $testResults = TestResult::whereHas('patient', function ($p) use ($icno) {
                $p->where('icno', $icno);
            })
                ->with('aiReview')  // Eager load AI review relationship
                ->orderBy('collected_date', 'desc')
                ->get();

            if ($testResults->isEmpty()) {
                Log::channel($this->getLogChannel())->info('searchReportId: No test results found', [
                    'icno' => $icno
                ]);

                return response()->json([]);
            }

            Log::channel($this->getLogChannel())->info('searchReportId: Test results found', [
                'icno' => $icno,
                'count' => $testResults->count()
            ]);

            // Build response array
            $results = [];

            foreach ($testResults as $testResult) {
                // Determine status based on completion and review flags
                $status = $this->determineTestResultStatus($testResult);

                // Get AI review HTML if exists
                $review = $testResult->aiReview ? $testResult->aiReview->ai_response : null;

                $results[] = [
                    'status' => $status,
                    'labno' => $testResult->lab_no,
                    'collected_date' => $testResult->collected_date ? Carbon::parse($testResult->collected_date)->format('Y-m-d') : null,
                    'reported_date' => $testResult->reported_date ? Carbon::parse($testResult->reported_date)->format('Y-m-d') : null,
                    'report_id' => $testResult->id,
                    'review' => $review
                ];

                Log::channel($this->getLogChannel())->debug('searchReportId: Processing test result', [
                    'test_result_id' => $testResult->id,
                    'lab_no' => $testResult->lab_no,
                    'status' => $status,
                    'is_completed' => $testResult->is_completed,
                    'is_reviewed' => $testResult->is_reviewed,
                    'has_ai_review' => $testResult->aiReview !== null
                ]);
            }

            $processingTime = now()->diffInSeconds($processingStartTime);

            Log::channel($this->getLogChannel())->info('searchReportId: Processing completed', [
                'icno' => $icno,
                'results_count' => count($results),
                'processing_time_seconds' => $processingTime
            ]);

            return response()->json($results);
        } catch (Throwable $e) {
            Log::channel($this->getLogChannel())->error('searchReportId: Critical error occurred', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'icno' => $icno
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing the request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update Test Result for manual sync
     */
    public function updateReportId(ODBRequest $request, $reportId)
    {
        $processingStartTime = now();
        $validated = $request->all();
        $item = $validated[0] ?? null;

        Log::channel($this->getLogChannel())->info('updateReportId: Processing started', [
            'report_id' => $reportId,
            'request_count' => count($validated)
        ]);

        try {
            // Step 1: Validate Request Data
            if (!$item) {
                Log::channel($this->getLogChannel())->warning('updateReportId: No item provided in request', [
                    'report_id' => $reportId
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'No data provided in request',
                    'report_id' => null,
                    'ai_response' => null
                ], 400);
            }

            $icno = $item['icno'] ?? null;
            $refid = $item['refid'] ?? null;

            Log::channel($this->getLogChannel())->info('updateReportId: Request validated', [
                'report_id' => $reportId,
                'icno' => $icno,
                'refid' => $refid
            ]);

            // Step 2: Fetch TestResult by ID
            $testResult = TestResult::find($reportId);

            if (!$testResult) {
                Log::channel($this->getLogChannel())->warning('updateReportId: TestResult not found', [
                    'report_id' => $reportId,
                    'icno' => $icno
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Test result not found',
                    'report_id' => $reportId,
                    'ai_response' => null
                ], 404);
            }

            Log::channel($this->getLogChannel())->info('updateReportId: TestResult found', [
                'report_id' => $testResult->id,
                'icno' => $icno,
                'current_ref_id' => $testResult->ref_id,
                'is_completed' => $testResult->is_completed,
                'is_reviewed' => $testResult->is_reviewed
            ]);

            // Step 3: Update ref_id Conditionally
            if (is_null($testResult->ref_id)) {
                if ($refid) {
                    $testResult->ref_id = $refid;
                    $testResult->manual_sync_date = Carbon::now();
                    $testResult->save();

                    Log::channel($this->getLogChannel())->info('updateReportId: ref_id updated', [
                        'report_id' => $testResult->id,
                        'new_ref_id' => $refid,
                        'manual_sync_date' => $testResult->manual_sync_date,
                        'icno' => $icno
                    ]);
                } else {
                    Log::channel($this->getLogChannel())->warning('updateReportId: ref_id is null and no refid provided', [
                        'report_id' => $reportId,
                        'icno' => $icno
                    ]);
                }
            } else {
                Log::channel($this->getLogChannel())->info('updateReportId: ref_id already set, skipping update', [
                    'report_id' => $testResult->id,
                    'existing_ref_id' => $testResult->ref_id,
                    'provided_refid' => $refid,
                    'icno' => $icno
                ]);
            }

            // Step 4: Get AIReview (No Generation)
            $aiReview = $testResult->aiReview;
            $aiResponse = null;

            if ($aiReview && $aiReview->http_status == 200 && $aiReview->ai_response) {
                $aiResponse = $aiReview->ai_response;

                Log::channel($this->getLogChannel())->info('updateReportId: AIReview found with successful status', [
                    'report_id' => $testResult->id,
                    'ai_review_id' => $aiReview->id,
                    'http_status' => $aiReview->http_status,
                    'icno' => $icno
                ]);
            } else {
                $reason = !$aiReview
                    ? 'AIReview not found'
                    : ($aiReview->http_status != 200
                        ? 'AIReview has failed status: ' . $aiReview->http_status
                        : 'AIReview has no response data');

                Log::channel($this->getLogChannel())->info('updateReportId: AI response not available', [
                    'report_id' => $testResult->id,
                    'reason' => $reason,
                    'icno' => $icno
                ]);
            }

            $processingTime = now()->diffInSeconds($processingStartTime);

            Log::channel($this->getLogChannel())->info('updateReportId: Processing completed successfully', [
                'report_id' => $testResult->id,
                'icno' => $icno,
                'ref_id_updated' => !is_null($testResult->ref_id),
                'has_ai_response' => $aiResponse !== null,
                'processing_time_seconds' => $processingTime
            ]);

            return response()->json([
                'report_id' => $testResult->id,
                'ai_response' => $aiResponse
            ]);
        } catch (Throwable $e) {
            Log::channel($this->getLogChannel())->error('updateReportId: Critical error occurred', [
                'report_id' => $reportId,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing the request',
                'report_id' => $reportId,
                'ai_response' => null,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper method to determine test result status based on completion and review flags
     *
     * @param TestResult $testResult
     * @return string
     */
    private function determineTestResultStatus(TestResult $testResult): string
    {
        if (!$testResult->is_completed) {
            return 'Processing';
        }

        if ($testResult->is_completed && $testResult->is_reviewed) {
            return 'Completed';
        }

        // is_completed = true, is_reviewed = false
        return 'Completed but no AI Report to be generated';
    }

    /**
     * Migrate old data from ODB to MyHealth
     */
    public function migrate(MigrateRequest $request)
    {
        try {
            $validated = $request->validated();

            // Generate unique batch UUID
            $batchUuid = Str::uuid()->toString();

            // Create migration batch
            $batch = MigrationBatch::create([
                'batch_uuid' => $batchUuid,
                'total_reports' => count($validated['reports']),
                'status' => MigrationBatch::STATUS_PENDING,
            ]);

            // Separate valid and invalid reports
            $validReports = [];
            $skippedReports = [];

            foreach ($validated['reports'] as $report) {
                $reportData = $report['report'];
                $missingFields = [];

                // Check critical fields
                if (empty($reportData['ic'])) $missingFields[] = 'ic';
                if (empty($reportData['name'])) $missingFields[] = 'name';
                if (empty($reportData['gender'])) $missingFields[] = 'gender';
                if (empty($reportData['dob']) || $reportData['dob'] === '0000-00-00') $missingFields[] = 'dob';
                if (empty($reportData['age']) || $reportData['age'] === '0,0,0') $missingFields[] = 'age';
                if (empty($reportData['lab_no'])) $missingFields[] = 'lab_no';
                if (empty($reportData['dr_name'])) $missingFields[] = 'dr_name';
                if (empty($reportData['clinic_name'])) $missingFields[] = 'clinic_name';
                if (empty($report['parameter']) || !is_array($report['parameter'])) $missingFields[] = 'parameter';

                if (!empty($missingFields)) {
                    $skippedReports[] = [
                        'report' => $report,
                        'reason' => 'Missing or invalid required fields: ' . implode(', ', $missingFields),
                    ];
                } else {
                    $validReports[] = $report;
                }
            }

            // Log skipped reports
            if (!empty($skippedReports)) {
                Log::channel('migrate-log')->warning('Skipped invalid reports in batch', [
                    'batch_uuid' => $batchUuid,
                    'skipped_count' => count($skippedReports),
                    'total_count' => count($validated['reports']),
                ]);

                foreach ($skippedReports as $skipped) {
                    Log::channel('migrate-log')->info('Skipped report details', [
                        'batch_uuid' => $batchUuid,
                        'ref_id' => $skipped['report']['ref_id'],
                        'reason' => $skipped['reason'],
                    ]);

                    // Create batch item with skipped status
                    MigrationBatchItem::create([
                        'batch_id' => $batch->id,
                        'ref_id' => $skipped['report']['ref_id'],
                        'report_data' => json_encode($skipped['report']),
                        'status' => MigrationBatchItem::STATUS_SKIPPED,
                        'error_message' => $skipped['reason'],
                        'processed_at' => now(),
                    ]);
                }
            }

            // Create batch items for valid reports
            foreach ($validReports as $report) {
                MigrationBatchItem::create([
                    'batch_id' => $batch->id,
                    'ref_id' => $report['ref_id'],
                    'report_data' => json_encode($report),
                    'status' => MigrationBatchItem::STATUS_PENDING,
                ]);
            }

            // Dispatch job to process batch asynchronously
            ProcessMigrationBatch::dispatch($batch->id);

            return response()->json([
                'success' => true,
                'message' => 'Migration batch created successfully',
                'data' => [
                    'batch_uuid' => $batchUuid,
                    'total_reports' => $batch->total_reports,
                    'status_url' => route('odb.migration.status', ['uuid' => $batchUuid]),
                ],
            ], 202);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create migration batch',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get migration batch status
     */
    public function migrationStatus($uuid)
    {
        try {
            $batch = MigrationBatch::where('batch_uuid', $uuid)->first();

            if (!$batch) {
                return response()->json([
                    'success' => false,
                    'message' => 'Batch not found',
                ], 404);
            }

            // Get failed items with error details
            $failedItems = $batch->failedItems()->get()->map(function ($item) {
                return [
                    'ref_id' => $item->ref_id,
                    'error' => $item->error_message,
                    'attempts' => $item->attempt_count,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'batch_uuid' => $batch->batch_uuid,
                    'status' => $batch->status,
                    'total' => $batch->total_reports,
                    'processed' => $batch->processed,
                    'success' => $batch->success,
                    'failed' => $batch->failed,
                    'started_at' => $batch->started_at?->toIso8601String(),
                    'completed_at' => $batch->completed_at?->toIso8601String(),
                    'failed_items' => $failedItems,
                ],
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch migration status',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test migration without jobs (for debugging with dd, var_dump, etc.)
     */
    public function migrateTest(MigrateRequest $request, MigrationService $migrationService)
    {
        try {
            $validated = $request->validated();

            // Process first report only for testing
            $report = $validated['reports'][0];

            // Process directly without job
            $testResult = $migrationService->processReport($report['report'], $report['parameter']);

            return response()->json([
                'success' => true,
                'message' => 'Test migration processed successfully',
                'data' => [
                    'test_result_id' => $testResult->id,
                    'ref_id' => $testResult->ref_id,
                    'lab_no' => $testResult->lab_no,
                ],
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Test migration failed',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 500);
        }
    }
}