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
                    $itemNumber = $index + 1;

                    Log::channel($this->getLogChannel())->info('getReportId: Processing item', [
                        'item_number' => $itemNumber,
                        'icno' => $icno,
                        'refid' => $refid
                    ]);

                    // Search by IC number first
                    Log::channel($this->getLogChannel())->debug('getReportId: Searching by IC number', [
                        'icno' => $icno
                    ]);

                    $testResult = TestResult::whereHas('patient', function ($p) use ($icno) {
                        $p->where('icno', $icno);
                    })->where('is_completed', true)->first();

                    if ($testResult) {
                        Log::channel($this->getLogChannel())->info('getReportId: Test result found by IC number', [
                            'icno' => $icno,
                            'test_result_id' => $testResult->id
                        ]);
                    }

                    // Fallback to search by refid if provided
                    if (!$testResult && $refid) {
                        Log::channel($this->getLogChannel())->debug('getReportId: IC search failed, falling back to refid search', [
                            'icno' => $icno,
                            'refid' => $refid
                        ]);

                        $testResult = TestResult::where('ref_id', $refid)->where('is_completed', true)->first();

                        if ($testResult) {
                            Log::channel($this->getLogChannel())->info('getReportId: Test result found by refid', [
                                'refid' => $refid,
                                'test_result_id' => $testResult->id
                            ]);
                        }
                    }

                    // Update ref_id if request has refid but DB has null
                    if ($testResult && $refid && $testResult->ref_id === null) {
                        Log::channel($this->getLogChannel())->debug('getReportId: Updating null ref_id in database', [
                            'test_result_id' => $testResult->id,
                            'old_ref_id' => null,
                            'new_ref_id' => $refid
                        ]);

                        $testResult->ref_id = $refid;
                        $testResult->save();

                        Log::channel($this->getLogChannel())->info('getReportId: ref_id updated successfully', [
                            'test_result_id' => $testResult->id,
                            'ref_id' => $refid
                        ]);
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
     * 
     * 
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
     * Get review by ID - Check if AI review exists, generate if not
     * Similar flow to getReportId but for reviews
     */
    public function getReviewById(ODBRequest $request)
    {
        // Increase execution time for potential AI generation
        ini_set('max_execution_time', 300); // 5 minutes

        $validated = $request->all();
        $processingStartTime = now();

        // Extract single item from request
        $item = $validated[0] ?? null;

        if (!$item) {
            Log::channel($this->getLogChannel())->warning('getReviewById: No item provided in request');
            return response()->json(null);
        }

        $icno = $item['icno'];
        $refid = $item['refid'] ?? null;

        Log::channel($this->getLogChannel())->info('getReviewById: Processing started', [
            'icno' => $icno,
            'refid' => $refid,
            'timestamp' => $processingStartTime
        ]);

        try {
            // Search for TestResult (same logic as getReportId)
            Log::channel($this->getLogChannel())->debug('getReviewById: Searching by IC number', [
                'icno' => $icno
            ]);

            $testResult = TestResult::whereHas('patient', function ($p) use ($icno) {
                $p->where('icno', $icno);
            })->latest()->first();

            if ($testResult) {
                Log::channel($this->getLogChannel())->info('getReviewById: Test result found by IC number', [
                    'icno' => $icno,
                    'test_result_id' => $testResult->id
                ]);
            }

            // Fallback to search by refid if provided
            if (!$testResult && $refid) {
                Log::channel($this->getLogChannel())->debug('getReviewById: IC search failed, falling back to refid search', [
                    'icno' => $icno,
                    'refid' => $refid
                ]);

                $testResult = TestResult::where('ref_id', $refid)
                    ->latest()->first();

                if ($testResult) {
                    Log::channel($this->getLogChannel())->info('getReviewById: Test result found by refid', [
                        'refid' => $refid,
                        'test_result_id' => $testResult->id
                    ]);
                }
            }

            // Return null if test result not found
            if (!$testResult) {
                Log::channel($this->getLogChannel())->warning('getReviewById: Test result not found', [
                    'icno' => $icno,
                    'refid' => $refid
                ]);

                return response()->json(null);
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

            // Check if AIReview exists for this test result using relationship
            $aiReview = $testResult->aiReview;

            if (!$aiReview) {
                Log::channel($this->getLogChannel())->info('getReviewById: AIReview not found, generating new review', [
                    'test_result_id' => $testResult->id
                ]);

                // Generate review synchronously
                $result = $this->aiReviewService->processSingle($testResult->id);

                if ($result->isSuccessful()) {
                    // Reload the relationship
                    $testResult->load('aiReview');
                    $aiReview = $testResult->aiReview;

                    Log::channel($this->getLogChannel())->info('getReviewById: AIReview generated successfully', [
                        'test_result_id' => $testResult->id,
                        'ai_review_id' => $aiReview->id
                    ]);
                } else {
                    Log::channel($this->getLogChannel())->error('getReviewById: Failed to generate AIReview', [
                        'test_result_id' => $testResult->id,
                        'error' => $result->errorMessage
                    ]);

                    return response()->json(null);
                }
            } else {
                Log::channel($this->getLogChannel())->info('getReviewById: AIReview found', [
                    'test_result_id' => $testResult->id,
                    'ai_review_id' => $aiReview->id
                ]);
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

            Log::channel($this->getLogChannel())->info('getReviewById: Processing completed', [
                'test_result_id' => $testResult->id,
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