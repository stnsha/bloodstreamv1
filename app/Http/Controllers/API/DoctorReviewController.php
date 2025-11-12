<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\DoctorReview;
use App\Models\Patient;
use App\Models\TestResult;
use App\Models\ResultLibrary;
use App\Services\MyHealthService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Http;

class DoctorReviewController extends Controller
{
    protected $myHealthService;

    public function __construct(MyHealthService $myHealthService)
    {
        $this->myHealthService = $myHealthService;
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
     */
    public function processResult()
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
            DB::beginTransaction();

            $testResults = TestResult::with([
                'patient',
                'testResultItems.panelPanelItem.panel.panelCategory',
                'testResultItems.referenceRange',
                'testResultItems.panelPanelItem.panelItem',
                'testResultItems.panelComments.masterPanelComment',
            ])
                ->where('is_reviewed', false)
                ->where('is_completed', true)
                // ->whereHas('patient', function ($query) {
                //     $query->where('ic_type', 'NRIC');
                // })
                // ->take(1) //First 5
                ->get();

            if ($testResults->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No test results found to process',
                    'summary' => [
                        'total_found' => 0,
                        'total_processed' => 0,
                        'successful_reviews_generated' => 0,
                        'successful_stores' => 0,
                        'failed_results' => 0,
                        'processing_time' => '0s'
                    ]
                ]);
            }

            // Login once before processing all results
            $login = Http::timeout(60)->post(config('credentials.ai_review.login'), [
                "username" => config('credentials.odb.username'),
                "password" => config('credentials.odb.password')
            ]);

            if ($login->failed()) {
                DB::rollBack();
                Log::channel($this->getLogChannel())->error('External AI service login failed');
                return response()->json([
                    'success' => false,
                    'message' => 'External AI service login failed - all processing stopped',
                    'summary' => [
                        'total_found' => $testResults->count(),
                        'total_processed' => 0,
                        'successful_reviews_generated' => 0,
                        'successful_stores' => 0,
                        'failed_results' => $testResults->count(),
                        'processing_time' => now()->diffInSeconds($processingStartTime) . 's',
                        'login_failed' => true
                    ]
                ], 401);
            }

            $loginData = $login->json();
            $token = $loginData['token'];

            foreach ($testResults as $tr) {
                $totalProcessed++;
                $healthDetails = []; // Initialize for each test result
                $finalResults = []; // Initialize for each test result

                try {
                    $icno = $tr->patient->icno;
                    $checkRecords = $this->myHealthService->getCheckRecordIdByIC($icno);

                    $patientInfo = [
                        'Age' => $tr->patient->age
                    ];

                    if ($checkRecords) {
                        foreach ($checkRecords as $cr) {
                            $recordId = $cr->id;
                            $recordGender = $cr->gender;
                            $recordDate = Carbon::parse($cr->date_time)->format('Y-m-d');

                            if (is_null($tr->patient->gender)) {
                                $tr->patient->gender = $recordGender == 1 ? Patient::GENDER_MALE : Patient::GENDER_FEMALE;
                                $tr->patient->save();
                            }

                            $patientInfo['Gender'] = $tr->patient->gender;

                            $recordDetails = $this->myHealthService->getRecordDetailsByRecordId($recordId);
                            if (count($recordDetails) != 0) {
                                $transformedRecordDetails = [];

                                foreach ($recordDetails as $rd) {
                                    if (isset($rd->parameter)) {
                                        $parameterName = $rd->parameter;
                                        unset($rd->parameter);
                                        $transformedRecordDetails[$parameterName] = $rd;
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
                        continue;
                    }

                    if (!$tr->patient) {
                        Log::channel($this->getLogChannel())->warning('Test result has no associated patient', ['test_result_id' => $tr->id]);
                        $failedResults[] = ['id' => $tr->id, 'reason' => 'Missing patient information'];
                        continue;
                    }

                    $reportDate = Carbon::parse($tr->reported_date)->format('Y-m-d');
                    $categorizedItems = [];
                    $validItemsCount = 0;

                    if ($tr->testResultItems->isEmpty()) {
                        Log::channel($this->getLogChannel())->warning('Test result has no test result items', ['test_result_id' => $tr->id]);
                        $failedResults[] = ['id' => $tr->id, 'reason' => 'No test result items found'];
                        continue;
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
                                    $resultLibrary = ResultLibrary::where('code', '0078')
                                        ->where('value', $ri->flag)
                                        ->first();
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
                        continue;
                    }

                    $finalResults[$reportDate] = $categorizedItems;

                    $testResultData = [
                        'Health History' => $patientInfo,
                        'Blood Test Results' => $finalResults
                    ];

                    // Call AI API using the token from login
                    $response = Http::timeout(120)->withToken($token)
                        ->post(config('credentials.ai_review.analysis'), $testResultData);


                    if ($response->failed()) {
                        Log::channel($this->getLogChannel())->error('AI analysis API call failed', [
                            'test_result_id' => $tr->id,
                            'response_status' => $response->status()
                        ]);
                        $failedResults[] = ['id' => $tr->id, 'reason' => 'AI analysis API call failed'];
                        continue;
                    }

                    $responseData = $response->json();
                    if ($responseData['ai_analysis']['success'] && $responseData['ai_analysis']['status'] == 200) {
                        $result = $this->convertTableBlock($responseData['ai_analysis']['answer']);
                        $successfulReviewsGenerated++;

                        // Store the generated review
                        $this->store($tr->id, $testResultData, $result);
                        $successfulStores++;

                        // Mark as reviewed (comment for testing)
                        $tr->is_reviewed = true;
                        $tr->save();

                        $successfulResults[] = [
                            'test_result_id' => $tr->id,
                            'patient_icno' => $tr->patient->icno
                        ];

                        // return $successfulResults;
                    } else {
                        Log::channel($this->getLogChannel())->error('AI analysis returned error status', [
                            'test_result_id' => $tr->id,
                            'response' => $responseData
                        ]);
                        $failedResults[] = ['id' => $tr->id, 'reason' => 'AI analysis returned error status'];
                        continue;
                    }
                } catch (Exception $e) {
                    Log::channel($this->getLogChannel())->error('Critical error processing individual test result', [
                        'error' => $e->getMessage(),
                        'test_result_id' => $tr->id ?? 'unknown',
                        'trace' => $e->getTraceAsString()
                    ]);
                    $failedResults[] = ['id' => $tr->id ?? 'unknown', 'reason' => 'Critical processing error: ' . $e->getMessage()];
                }
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            Log::channel($this->getLogChannel())->error('Critical error in processResult method', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Critical error occurred during processing',
                'error' => $e->getMessage(),
                'summary' => [
                    'total_found' => $testResults->count() ?? 0,
                    'total_processed' => $totalProcessed,
                    'successful_reviews_generated' => $successfulReviewsGenerated,
                    'successful_stores' => $successfulStores,
                    'failed_results' => count($failedResults),
                    'processing_time' => now()->diffInSeconds($processingStartTime) . 's'
                ]
            ], 500);
        }

        // Generate final summary
        $processingTime = now()->diffInSeconds($processingStartTime);
        $totalFound = $testResults->count();
        $failedCount = count($failedResults);
        $successRate = $totalFound > 0 ? round(($successfulStores / $totalFound) * 100, 2) : 0;

        // Log summary of processing
        Log::channel($this->getLogChannel())->info('DoctorReviewController processResult completed', [
            'total_found' => $totalFound,
            'successful_stores' => $successfulStores,
            'failed_results' => $failedCount,
            'success_rate' => $successRate . '%',
            'processing_time' => $processingTime . 's',
            'successful_result_ids' => array_column($successfulResults, 'test_result_id')
        ]);

        return response()->json([
            'success' => $failedCount === 0,
            'message' => $failedCount === 0
                ? "All {$totalFound} test results processed successfully"
                : "Processed {$totalFound} test results with {$failedCount} failures",
            'summary' => [
                'total_found' => $totalFound,
                'total_processed' => $totalProcessed,
                'successful_reviews_generated' => $successfulReviewsGenerated,
                'successful_stores' => $successfulStores,
                'successful_results' => $successfulResults,
                'failed_results' => $failedCount,
                'success_rate' => $successRate . '%',
                'processing_time' => $processingTime . 's',
                'failed_details' => $failedResults
            ]
        ], $failedCount === 0 ? 200 : 207); // 207 = Multi-Status (partial success)
    }

    public function convertTableBlock(array $data): string
    {
        // $data = $request['ai_analysis']['answer'];
        $html = '';

        // $html = '<style>
        //             body { font-family: Arial, sans-serif; color: #333; }
        //             h5 { font-size: 18px; margin-bottom: 10px; color: #2b2b2b; }
        //             table { width: 100%; border-collapse: collapse; margin-bottom: 25px; }
        //             th, td { border: 1px solid #ccc; padding: 10px 12px; text-align: left; vertical-align: top; }
        //             th { background-color: #f5f5f5; font-weight: bold; }
        //             tr:nth-child(even) { background-color: #fafafa; }
        //             .section { margin-bottom: 30px; }
        //             .card { border: 1px solid #ddd; padding: 15px; border-radius: 8px; background-color: #fff; }
        //             .highlight { margin-bottom: 8px; }
        //             .disclaimer { font-size: 13px; color: #666; margin-top: 15px; }
        //         </style>';

        // SECTION A1
        if (!empty($data['section_a1'])) {
            $html .= '<div class="section">';
            $html .= '<h5>Your Health at a Glance</h5>';
            $html .= '<table><thead><tr>
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
            $html .= '<div class="section">';
            $html .= '<h5>Your Body System Highlights</h5>';
            $html .= '<ol>';
            foreach ($data['section_a2'] as $highlight) {
                $html .= '<li class="highlight">' . $highlight . '</li>';
            }
            $html .= '</ol></div>';
        }

        // SECTION B
        if (!empty($data['section_b'])) {
            $html .= '<div class="section">';
            $html .= '<h5>3-6 Month Health Action</h5>';
            $html .= '<table><thead><tr>
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
            $html .= '<div class="section card">';
            $html .= '<h5>With Care, from Alpro</h5>';
            $html .= '<p>' . nl2br(e($data['section_c'])) . '</p>';
            $html .= '<p class="disclaimer">
                    Disclaimer: This report is for educational purposes only and should not replace consultation with a qualified healthcare professional.
                  </p>';
            $html .= '</div>';
        }

        return $html;
    }
}