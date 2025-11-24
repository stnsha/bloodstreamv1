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
     * Retrieve IC and refID from ODB to generate AI review
     *
     * REFACTORED: Now uses AIReviewService to eliminate code duplication
     * Old implementation kept below as backup (search for "OLD CODE - BACKUP")
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

    /* ========== OLD CODE - BACKUP ==========
     * Original review implementation before refactoring to AIReviewService
     * Kept for rollback purposes if needed
     * Can be removed after verification that new implementation works correctly
     *
    public function review_OLD(ODBRequest $request)
    {
        // Increase execution time for external API calls
        ini_set('max_execution_time', 300); // 5 minutes

        // Initialize tracking variables for summary
        $totalProcessed = 0;
        $successfulReviewsGenerated = 0;
        $successfulStores = 0;
        $successfulResults = [];
        $failedResults = [];
        $processingStartTime = now();

        Log::channel($this->getLogChannel())->info('AI Review process started', [
            'total_items' => count($request->all()),
            'timestamp' => $processingStartTime
        ]);

        try {
            $validated = $request->all();

            // Get cached token from ApiTokenService (PERFORMANCE OPTIMIZATION - cached for 30 days)
            Log::channel($this->getLogChannel())->info('Getting AI service token');

            $token = $this->apiTokenService->getValidToken();

            if (!$token) {
                Log::channel($this->getLogChannel())->error('Failed to obtain AI service token');

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to authenticate with AI service',
                    'error' => 'Unable to obtain valid API token'
                ], 401);
            }

            Log::channel($this->getLogChannel())->info('AI service token obtained successfully');

            // Step 1: Collect all test result data IN TRANSACTION (fast, no HTTP calls)
            $testResultsToProcess = DB::transaction(function () use ($validated, &$totalProcessed, &$failedResults) {
                $testResultsData = [];

                foreach ($validated as $item) {
                    $icno = $item['icno'];
                    $refid = $item['refid'] ?? null;
                    $totalProcessed++;

                    Log::channel($this->getLogChannel())->info('Processing test result', [
                        'icno' => $icno,
                        'refid' => $refid,
                        'item_number' => $totalProcessed
                    ]);

                    $tr = TestResult::with([
                        'patient',
                        'testResultItems.panelPanelItem.panel.panelCategory',
                        'testResultItems.referenceRange',
                        'testResultItems.panelPanelItem.panelItem',
                        'testResultItems.panelComments.masterPanelComment',
                    ])
                        ->where('is_reviewed', false)
                        ->where('is_completed', true)
                        ->whereHas('patient', function ($query) use ($icno) {
                            $query->where('icno', $icno);
                        })
                        ->first();

                    // Fallback to search by refid if provided
                    if (!$tr && $refid) {
                        $tr = TestResult::where('ref_id', $refid)
                            ->where('is_reviewed', false)
                            ->where('is_completed', true)
                            ->first();
                    }

                    if (!$tr) {
                        Log::channel($this->getLogChannel())->warning('Test result not found', [
                            'icno' => $icno,
                            'refid' => $refid
                        ]);
                        $failedResults[] = [
                            'icno' => $icno,
                            'refid' => $refid,
                            'reason' => 'Test result not found or already reviewed'
                        ];
                        continue;
                    }

                    Log::channel($this->getLogChannel())->info('Test result found', [
                        'test_result_id' => $tr->id,
                        'icno' => $icno,
                        'refid' => $refid
                    ]);

                    //Prepare MyHealth history
                    $checkRecords = $this->myHealthService->getCheckRecordIdByIC($icno);

                    $patientInfo = [
                        'Age' => $tr->patient->age
                    ];

                    if ($checkRecords && $checkRecords->isNotEmpty()) {
                        // Batch load all record details to avoid N+1 query (PERFORMANCE OPTIMIZATION)
                        $recordIds = $checkRecords->pluck('id')->toArray();
                        $allRecordDetails = $this->myHealthService->getRecordDetailsBatch($recordIds);

                        foreach ($checkRecords as $cr) {
                            $recordId = $cr->id;
                            $recordGender = $cr->gender;
                            $recordDate = Carbon::parse($cr->date_time)->format('Y-m-d');

                            if (is_null($tr->patient->gender)) {
                                $tr->patient->gender = $recordGender == 1 ? Patient::GENDER_MALE : Patient::GENDER_FEMALE;
                                $tr->patient->save();
                            }

                            $patientInfo['Gender'] = $tr->patient->gender;

                            // Use pre-loaded record details (NO QUERY - PERFORMANCE OPTIMIZATION)
                            $recordDetails = $allRecordDetails[$recordId] ?? collect([]);
                            if ($recordDetails->isNotEmpty()) {
                                $transformedRecordDetails = [];

                                foreach ($recordDetails as $rd) {
                                    if (isset($rd->parameter)) {
                                        $parameterName = $rd->parameter;
                                        // Create a copy without record_id and parameter
                                        $rdCopy = (object)[
                                            'min_range' => $rd->min_range,
                                            'max_range' => $rd->max_range,
                                            'range' => $rd->range,
                                            'unit' => $rd->unit,
                                            'result' => $rd->result
                                        ];
                                        $transformedRecordDetails[$parameterName] = $rdCopy;
                                    }
                                }
                                $healthDetails[$recordDate] = $transformedRecordDetails;
                                $patientInfo = array_merge($patientInfo, $healthDetails);
                            }
                        }
                    }

                    if (!$tr || !$tr->id) {
                        Log::channel($this->getLogChannel())->error('Invalid test result object', [
                            'icno' => $icno,
                            'refid' => $refid
                        ]);
                        $failedResults[] = [
                            'icno' => $icno,
                            'refid' => $refid,
                            'reason' => 'Invalid test result object'
                        ];
                        continue;
                    }

                    if (!$tr->patient) {
                        Log::channel($this->getLogChannel())->warning('Test result has no associated patient', [
                            'test_result_id' => $tr->id,
                            'icno' => $icno,
                            'refid' => $refid
                        ]);
                        $failedResults[] = [
                            'icno' => $icno,
                            'refid' => $refid,
                            'test_result_id' => $tr->id,
                            'reason' => 'Missing patient information'
                        ];
                        continue;
                    }

                    $reportDate = Carbon::parse($tr->reported_date)->format('Y-m-d');
                    $categorizedItems = [];
                    $validItemsCount = 0;

                    if ($tr->testResultItems->isEmpty()) {
                        Log::channel($this->getLogChannel())->warning('Test result has no test result items', [
                            'test_result_id' => $tr->id,
                            'icno' => $icno,
                            'refid' => $refid
                        ]);
                        $failedResults[] = [
                            'icno' => $icno,
                            'refid' => $refid,
                            'test_result_id' => $tr->id,
                            'reason' => 'No test result items found'
                        ];
                        continue;
                    }

                    // Pre-load all ResultLibrary records to avoid N+1 query (PERFORMANCE OPTIMIZATION)
                    $flags = $tr->testResultItems->pluck('flag')->filter()->unique();
                    $resultLibraries = [];
                    if ($flags->isNotEmpty()) {
                        $resultLibraries = ResultLibrary::where('code', '0078')
                            ->whereIn('value', $flags->toArray())
                            ->get()
                            ->keyBy('value');
                    }

                    foreach ($tr->testResultItems as $ri) {
                        try {
                            if (!$ri || !$ri->id) {
                                continue;
                            }

                            if (!$ri->panelPanelItem) {
                                continue;
                            }

                            if (!$ri->panelPanelItem->panelItem) {
                                continue;
                            }

                            $panelItemName = $ri->panelPanelItem->panelItem->name ?? 'Unknown Item';

                            // Determine panel name: use actual panel name or fallback to panel item name
                            if ($ri->panelPanelItem->panel && $ri->panelPanelItem->panel->name) {
                                $panelName = $ri->panelPanelItem->panel->name;
                            } else {
                                $panelName = $panelItemName; // Use panel item name as panel name
                            }

                            // Build simplified panel-only structure
                            if (!isset($categorizedItems[$panelName])) {
                                $categorizedItems[$panelName] = [];
                            }

                            $flagDescription = $ri->flag;
                            if (!empty($ri->flag)) {
                                try {
                                    // Use pre-loaded ResultLibrary (NO QUERY - PERFORMANCE OPTIMIZATION)
                                    $resultLibrary = $resultLibraries[$ri->flag] ?? null;
                                    if ($resultLibrary && !empty($resultLibrary->description)) {
                                        // Remove content within parentheses and trim whitespace
                                        $flagDescription = trim(preg_replace('/\s*\([^)]*\)/', '', $resultLibrary->description));
                                    } else {
                                        $flagDescription = $ri->flag;
                                    }
                                } catch (Exception $e) {
                                    // Intentionally silent - flag description lookup failure should not stop processing
                                }
                            }

                            $itemData = [
                                'panel_item_name' => $ri->panelPanelItem->panelItem->name ?? 'Unknown Item',
                                'result_value' => $ri->value ?? null,
                                'panel_item_unit' => $ri->panelPanelItem->panelItem->unit ?? null,
                                'result_status' => $flagDescription ?? null,
                                'reference_range' => null,
                                'comments' => []
                            ];

                            if ($ri->reference_range_id && $ri->referenceRange) {
                                try {
                                    $itemData['reference_range'] = $ri->referenceRange->value;
                                } catch (Exception $e) {
                                    // Intentionally silent - reference range lookup failure should not stop processing
                                }
                            }

                            if ($ri->panelComments && !$ri->panelComments->isEmpty()) {
                                try {
                                    foreach ($ri->panelComments as $pc) {
                                        if ($pc && $pc->masterPanelComment && !empty($pc->masterPanelComment->comment)) {
                                            $itemData['comments'][] = $pc->masterPanelComment->comment;
                                        }
                                    }
                                } catch (Exception $e) {
                                    // Intentionally silent - comments processing failure should not stop processing
                                }
                            }

                            // Add item to panel (simplified structure)
                            $categorizedItems[$panelName][] = $itemData;
                            $validItemsCount++;
                        } catch (Exception $e) {
                            // Intentionally silent - individual item processing failure should not stop processing
                        }
                    }

                    if ($validItemsCount === 0) {
                        Log::channel($this->getLogChannel())->warning('No valid test result items processed', [
                            'test_result_id' => $tr->id,
                            'icno' => $icno,
                            'refid' => $refid
                        ]);
                        $failedResults[] = [
                            'icno' => $icno,
                            'refid' => $refid,
                            'test_result_id' => $tr->id,
                            'reason' => 'No valid test result items'
                        ];
                        continue;
                    }

                    $finalResults[$reportDate] = $categorizedItems;

                    $testResultData = [
                        'Health History' => $patientInfo,
                        'Blood Test Results' => $finalResults
                    ];

                    // Collect data for API processing (NO HTTP CALLS IN TRANSACTION)
                    $testResultsData[] = [
                        'test_result' => $tr,
                        'icno' => $icno,
                        'refid' => $refid,
                        'test_result_data' => $testResultData
                    ];
                }

                return $testResultsData;
            });

            // Step 2: Process AI API calls OUTSIDE transaction (no database locks)
            foreach ($testResultsToProcess as $data) {
                $tr = $data['test_result'];
                $icno = $data['icno'];
                $refid = $data['refid'];
                $testResultData = $data['test_result_data'];

                // Call AI API using the token from login
                Log::channel($this->getLogChannel())->info('Calling AI analysis API', [
                    'test_result_id' => $tr->id,
                    'icno' => $icno,
                    'refid' => $refid
                ]);

                $response = Http::timeout(120)->withToken($token)
                    ->post(config('credentials.ai_review.analysis'), $testResultData);

                // Step 3: Save results in SEPARATE transaction (fast, no HTTP)
                if ($response->failed()) {
                    Log::channel($this->getLogChannel())->error('AI analysis API call failed', [
                        'test_result_id' => $tr->id,
                        'icno' => $icno,
                        'refid' => $refid,
                        'response_status' => $response->status(),
                        'response_body' => $response->body()
                    ]);

                    // Log failed attempt to AIReview
                    DB::transaction(function () use ($tr, $testResultData, $response) {
                        AIReview::create([
                            'test_result_id' => $tr->id,
                            'compiled_results' => $testResultData,
                            'http_status' => $response->status(),
                            'ai_response' => null,
                            'error_message' => 'AI analysis API call failed with status ' . $response->status(),
                            'is_successful' => false
                        ]);
                    });

                    $failedResults[] = [
                        'icno' => $icno,
                        'refid' => $refid,
                        'test_result_id' => $tr->id,
                        'reason' => 'AI analysis API call failed'
                    ];
                    continue;
                }

                $responseData = $response->json();
                if ($responseData['ai_analysis']['success'] && $responseData['ai_analysis']['status'] == 200) {
                    Log::channel($this->getLogChannel())->info('AI analysis successful', [
                        'test_result_id' => $tr->id,
                        'icno' => $icno,
                        'refid' => $refid
                    ]);

                    $result = $this->convertTableBlock($responseData['ai_analysis']['answer']);
                    $successfulReviewsGenerated++;

                    // Store the successful review in separate transaction
                    DB::transaction(function () use ($tr, $testResultData, $responseData, $result, $refid, &$successfulStores) {
                        AIReview::firstOrCreate(
                            [
                                'test_result_id' => $tr->id
                            ],
                            [
                                'compiled_results' => $testResultData,
                                'http_status' => $responseData['ai_analysis']['status'],
                                'ai_response' => $result,
                                'error_message' => null,
                                'is_successful' => true
                            ]
                        );

                        $successfulStores++;

                        // Mark as reviewed
                        $tr->is_reviewed = true;
                        // Update ref_id if request has refid but DB has null
                        $tr->ref_id = $refid;
                        $tr->save();
                    });

                    $successfulResults[] = [
                        'icno' => $icno,
                        'refid' => $refid,
                        'report_id' => $tr->id,
                        'review' => $result
                    ];

                    Log::channel($this->getLogChannel())->info('Test result marked as reviewed', [
                        'test_result_id' => $tr->id,
                        'report_id' => $tr->id
                    ]);
                } else {
                    Log::channel($this->getLogChannel())->error('AI analysis returned error status', [
                        'test_result_id' => $tr->id,
                        'icno' => $icno,
                        'refid' => $refid,
                        'response' => $responseData
                    ]);

                    // Log failed attempt to AIReview
                    DB::transaction(function () use ($tr, $testResultData, $responseData) {
                        AIReview::create([
                            'test_result_id' => $tr->id,
                            'compiled_results' => $testResultData,
                            'http_status' => $responseData['ai_analysis']['status'] ?? 500,
                            'ai_response' => $responseData,
                            'error_message' => 'AI analysis returned error status',
                            'is_successful' => false
                        ]);
                    });

                    $failedResults[] = [
                        'icno' => $icno,
                        'refid' => $refid,
                        'test_result_id' => $tr->id,
                        'reason' => 'AI analysis returned error status'
                    ];
                    continue;
                }
            }

            // After transaction completes, check if any items succeeded
            if (count($successfulResults) > 0) {
                return response()->json($successfulResults);
            }

            // If we reach here, all items were processed but none succeeded
            Log::channel($this->getLogChannel())->warning('All items processed but none succeeded', [
                'total_processed' => $totalProcessed,
                'failed_count' => count($failedResults)
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No test results could be processed successfully',
                'failed_results' => $failedResults,
                'summary' => [
                    'total_processed' => $totalProcessed,
                    'failed' => count($failedResults)
                ]
            ], 404);
        } catch (Exception $e) {
            Log::channel($this->getLogChannel())->error('Critical error in review method', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'total_processed' => $totalProcessed,
                'successful_reviews' => $successfulReviewsGenerated,
                'failed_count' => count($failedResults)
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Critical error occurred during processing',
                'error' => $e->getMessage(),
                'summary' => [
                    'total_processed' => $totalProcessed,
                    'successful_reviews_generated' => $successfulReviewsGenerated,
                    'successful_stores' => $successfulStores,
                    'failed_results' => count($failedResults),
                    'processing_time' => now()->diffInSeconds($processingStartTime) . 's'
                ]
            ], 500);
        }
    }
    ========== END OLD CODE - BACKUP ========== */

    /* ========== OLD CODE - BACKUP ==========
     * Original convertTableBlock implementation before refactoring to ReviewHtmlGenerator service
     * Now handled by App\Services\ReviewHtmlGenerator
     * Kept for rollback purposes if needed
     *
    public function convertTableBlock_OLD(array $data): string
    {
        // $data = $request['ai_analysis']['answer'];
        $html = '';

        // SECTION A1
        if (!empty($data['section_a1'])) {
            $html .= '<div class="review-section">';
            $html .= '<h5>Your Health at a Glance</h5>';
            $html .= '<table class="review-table"><thead><tr>
                    <th>Health Area</th>
                    <th>Status</th>
                    <th>Notes</th>
                  </tr></thead><tbody>';

            foreach ($data['section_a1'] as $row) {
                $statusIcon = match (strtolower($row['status'])) {
                    'normal', 'optimal' => '🟢',
                    'borderline' => '🟡',
                    'need attention', 'needs attention' => '🔴',
                    default => '⚪️',
                };

                $html .= '<tr>';
                $html .= '<td>' . e($row['health_area']) . '</td>';
                $html .= '<td>' . $statusIcon . ' ' . e(ucwords($row['status'])) . '</td>';
                $html .= '<td>' . e($row['notes']) . '</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody></table></div>';
        }

        // SECTION A2
        if (!empty($data['section_a2'])) {
            $html .= '<div class="review-section">';
            $html .= '<h5>Your Body System Highlights</h5>';
            $html .= '<ol class="review-list">';
            foreach ($data['section_a2'] as $highlight) {
                $html .= '<li class="highlight">' . $highlight . '</li>';
            }
            $html .= '</ol></div>';
        }

        // SECTION B
        if (!empty($data['section_b'])) {
            $html .= '<div class="review-section">';
            $html .= '<h5>3-6 Month Health Action</h5>';
            $html .= '<table class="review-table"><thead><tr>
                <th>Timeline</th>
                <th>Action</th>
                <th>Goals</th>
                <th>Alpro Care for You</th>
                <th>Appointment Date & Place</th>
              </tr></thead><tbody>';

            foreach ($data['section_b'] as $row) {

                // Convert 'action' into list if contains ';'
                $action = $row['action'] ?? '-';
                if (strpos($action, ';') !== false) {
                    $items = array_filter(array_map('trim', explode(';', $action)));
                    $action = '<ul>';
                    foreach ($items as $item) {
                        $action .= '<li>' . e($item) . '</li>';
                    }
                    $action .= '</ul>';
                } else {
                    $action = e($action);
                }

                // Convert 'goals' into list if contains ';'
                $goals = $row['goals'] ?? '-';
                if (strpos($goals, ';') !== false) {
                    $items = array_filter(array_map('trim', explode(';', $goals)));
                    $goals = '<ul>';
                    foreach ($items as $item) {
                        $goals .= '<li>' . e($item) . '</li>';
                    }
                    $goals .= '</ul>';
                } else {
                    $goals = e($goals);
                }

                $html .= '<tr>';
                $html .= '<td>' . e($row['timeline'] ?? '-') . '</td>';
                $html .= '<td>' . $action . '</td>';
                $html .= '<td>' . $goals . '</td>';
                $html .= '<td>' . e($row['care'] ?? '-') . '</td>';
                $html .= '<td></td>';
                $html .= '</tr>';
            }

            $html .= '</tbody></table></div>';
        }


        // SECTION C
        if (!empty($data['section_c'])) {
            $html .= '<div class="review-section">';
            $html .= '<h5>With Care, from Alpro</h5>';
            $html .= '<p>' . nl2br(e($data['section_c'])) . '</p>';
            $html .= '<p class="disclaimer">
                    Disclaimer: This report is for educational purposes only and should not replace consultation with a qualified healthcare professional.
                  </p>';
            $html .= '</div>';
        }

        return $html;
    }
    ========== END OLD CODE - BACKUP ========== */

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