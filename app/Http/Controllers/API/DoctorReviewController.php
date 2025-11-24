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

    /* ========== OLD CODE - BACKUP ==========
     * Original processResult implementation before refactoring to AIReviewService
     * Kept for rollback purposes if needed
     * Can be removed after verification that new implementation works correctly
     *
    public function processResult_OLD($testResultId)
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

        try {
            // Get cached token from ApiTokenService (PERFORMANCE OPTIMIZATION - cached for 30 days)
            $token = $this->apiTokenService->getValidToken();

            if (!$token) {
                Log::channel($this->getLogChannel())->error('Failed to obtain AI service token', [
                    'test_result_id' => $testResultId
                ]);
                return;
            }

            DB::transaction(function () use ($testResultId, $token, &$totalProcessed, &$successfulReviewsGenerated, &$successfulStores, &$successfulResults, &$failedResults) {
                $tr = TestResult::with([
                    'patient',
                    'testResultItems.panelPanelItem.panel.panelCategory',
                    'testResultItems.referenceRange',
                    'testResultItems.panelPanelItem.panelItem',
                    'testResultItems.panelComments.masterPanelComment',
                ])
                    ->where('is_reviewed', false)
                    ->where('is_completed', true)
                    ->where('id', $testResultId)
                    ->first();

                if (!$tr) {
                    Log::channel($this->getLogChannel())->warning('No test result found to process', [
                        'test_result_id' => $testResultId
                    ]);
                    return;
                }

                $totalProcessed++;
                $healthDetails = []; // Initialize for each test result
                $finalResults = []; // Initialize for each test result

                try {
                    $icno = $tr->patient->icno;
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
                    Log::channel($this->getLogChannel())->error('Invalid test result object');
                    $failedResults[] = ['id' => 'unknown', 'reason' => 'Invalid test result object'];
                }

                if (!$tr->patient) {
                    Log::channel($this->getLogChannel())->warning('Test result has no associated patient', ['test_result_id' => $tr->id]);
                    $failedResults[] = ['id' => $tr->id, 'reason' => 'Missing patient information'];
                }

                $reportDate = Carbon::parse($tr->reported_date)->format('Y-m-d');
                $categorizedItems = [];
                $validItemsCount = 0;

                if ($tr->testResultItems->isEmpty()) {
                    Log::channel($this->getLogChannel())->warning('Test result has no test result items', ['test_result_id' => $tr->id]);
                    $failedResults[] = ['id' => $tr->id, 'reason' => 'No test result items found'];
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
                            Log::channel($this->getLogChannel())->warning('Invalid result item', ['test_result_id' => $tr->id]);
                            continue;
                        }

                        if (!$ri->panelPanelItem) {
                            Log::channel($this->getLogChannel())->warning('Test result item missing panel relationship', [
                                'result_item_id' => $ri->id,
                                'test_result_id' => $tr->id
                            ]);
                            continue;
                        }

                        if (!$ri->panelPanelItem->panelItem) {
                            Log::channel($this->getLogChannel())->warning('Test result item missing panel item relationship', [
                                'result_item_id' => $ri->id,
                                'test_result_id' => $tr->id
                            ]);
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
                                Log::channel($this->getLogChannel())->error('Error fetching flag description from ResultLibrary', [
                                    'error' => $e->getMessage(),
                                    'flag' => $ri->flag,
                                    'result_item_id' => $ri->id
                                ]);
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
                                Log::channel($this->getLogChannel())->warning('Error accessing reference range', [
                                    'error' => $e->getMessage(),
                                    'result_item_id' => $ri->id
                                ]);
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
                                Log::channel($this->getLogChannel())->warning('Error processing panel comments', [
                                    'error' => $e->getMessage(),
                                    'result_item_id' => $ri->id
                                ]);
                            }
                        }

                        // Add item to panel (simplified structure)
                        $categorizedItems[$panelName][] = $itemData;
                        $validItemsCount++;
                    } catch (Exception $e) {
                        Log::channel($this->getLogChannel())->error('Error processing test result item', [
                            'error' => $e->getMessage(),
                            'result_item_id' => $ri->id ?? 'unknown',
                            'test_result_id' => $tr->id,
                            'trace' => $e->getTraceAsString()
                        ]);
                    }
                }

                if ($validItemsCount === 0) {
                    Log::channel($this->getLogChannel())->warning('No valid test result items processed', ['test_result_id' => $tr->id]);
                    $failedResults[] = ['id' => $tr->id, 'reason' => 'No valid test result items'];
                }

                $finalResults[$reportDate] = $categorizedItems;

                $testResultData = [
                    'Health History' => $patientInfo,
                    'Blood Test Results' => $finalResults
                ];

                // Call AI API using the token from login
                Log::channel($this->getLogChannel())->info('Calling AI analysis API', [
                    'test_result_id' => $tr->id,
                    'icno' => $icno,
                ]);

                $response = Http::timeout(120)->withToken($token)
                    ->post(config('credentials.ai_review.analysis'), $testResultData);

                if ($response->failed()) {
                    Log::channel($this->getLogChannel())->error('AI analysis API call failed', [
                        'test_result_id' => $tr->id,
                        'icno' => $icno,
                        'response_status' => $response->status(),
                        'response_body' => $response->body()
                    ]);

                    // Log failed attempt to AIReview
                    AIReview::create([
                        'test_result_id' => $tr->id,
                        'compiled_results' => $testResultData,
                        'http_status' => $response->status(),
                        'ai_response' => null,
                        'error_message' => 'AI analysis API call failed with status ' . $response->status(),
                        'is_successful' => false
                    ]);

                    $failedResults[] = [
                        'icno' => $icno,
                        'test_result_id' => $tr->id,
                        'reason' => 'AI analysis API call failed'
                    ];
                }

                $responseData = $response->json();
                if ($responseData['ai_analysis']['success'] && $responseData['ai_analysis']['status'] == 200) {
                    Log::channel($this->getLogChannel())->info('AI analysis successful', [
                        'test_result_id' => $tr->id,
                        'icno' => $icno
                    ]);

                    $result = $this->convertTableBlock($responseData['ai_analysis']['answer']);
                    $successfulReviewsGenerated++;

                    // Store the successful review
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
                    $tr->save();

                    $successfulResults[] = [
                        'icno' => $icno,
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
                        'response' => $responseData
                    ]);
                    $failedResults[] = ['id' => $tr->id, 'reason' => 'AI analysis returned error status'];
                }
            } catch (Exception $e) {
                Log::channel($this->getLogChannel())->error('Critical error processing individual test result', [
                    'error' => $e->getMessage(),
                    'test_result_id' => $tr->id ?? 'unknown',
                    'trace' => $e->getTraceAsString()
                ]);
                $failedResults[] = ['id' => $tr->id ?? 'unknown', 'reason' => 'Critical processing error: ' . $e->getMessage()];
            }
        });
        } catch (Exception $e) {
            Log::channel($this->getLogChannel())->error('Critical error in processResult method', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'test_result_id' => $testResultId
            ]);
        }

        // Generate final summary
        $processingTime = now()->diffInSeconds($processingStartTime);
        $totalFound = 1;
        $failedCount = count($failedResults);
        $successRate = $totalFound > 0 ? round(($successfulStores / $totalFound) * 100, 2) : 0;

        // Log summary of processing
        Log::channel($this->getLogChannel())->info('DoctorReviewController processResult completed', [
            'test_result_id' => $testResultId,
            'total_found' => $totalFound,
            'total_processed' => $totalProcessed,
            'successful_reviews_generated' => $successfulReviewsGenerated,
            'successful_stores' => $successfulStores,
            'failed_results' => $failedCount,
            'success_rate' => $successRate . '%',
            'processing_time' => $processingTime . 's',
            'successful_result_ids' => array_column($successfulResults, 'report_id')
        ]);
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
}