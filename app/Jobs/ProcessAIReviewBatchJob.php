<?php

namespace App\Jobs;

use App\Http\Controllers\API\DoctorReviewController;
use App\Models\TestResult;
use App\Models\Patient;
use App\Models\ResultLibrary;
use App\Services\MyHealthService;
use App\Services\ApiTokenService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Carbon\Carbon;
use Exception;
use Throwable;

class ProcessAIReviewBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 900; // 15 minutes per batch
    public $maxExceptions = 2;
    public $backoff = [120, 600]; // 2min, 10min

    private $testResultIds;
    private $batchNumber;
    private $myHealthService;
    private $apiTokenService;
    private $doctorReviewController;

    /**
     * Create a new job instance.
     */
    public function __construct(array $testResultIds, int $batchNumber)
    {
        $this->testResultIds = $testResultIds;
        $this->batchNumber = $batchNumber;
    }

    /**
     * Execute the job.
     */
    public function handle(
        MyHealthService $myHealthService,
        ApiTokenService $apiTokenService,
        DoctorReviewController $doctorReviewController
    ): void {
        $this->myHealthService = $myHealthService;
        $this->apiTokenService = $apiTokenService;
        $this->doctorReviewController = $doctorReviewController;

        $startTime = now();
        Log::channel('job')->info("ProcessAIReviewBatchJob batch {$this->batchNumber} started", [
            'batch_number' => $this->batchNumber,
            'test_result_ids' => $this->testResultIds,
            'count' => count($this->testResultIds),
            'start_time' => $startTime
        ]);

        // Initialize tracking variables
        $successfulProcessed = 0;
        $failedResults = [];
        $successfulResults = [];

        try {
            // Get API token (cached or fresh)
            $token = $this->apiTokenService->getValidToken();
            if (!$token) {
                throw new Exception('Failed to obtain valid API token');
            }

            // Process each test result in this batch with transaction per result
            foreach ($this->testResultIds as $testResultId) {
                try {
                    $processed = $this->processTestResult($testResultId, $token);
                    if ($processed) {
                        $successfulProcessed++;
                        $successfulResults[] = $testResultId;
                    } else {
                        $failedResults[] = $testResultId;
                    }
                } catch (Exception $e) {
                    Log::channel('job')->error("Failed to process test result {$testResultId}", [
                        'test_result_id' => $testResultId,
                        'batch_number' => $this->batchNumber,
                        'error' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    $failedResults[] = $testResultId;
                }
            }

            $endTime = now();
            $duration = $endTime->diffInSeconds($startTime);

            Log::channel('job')->info("ProcessAIReviewBatchJob batch {$this->batchNumber} completed", [
                'batch_number' => $this->batchNumber,
                'total_in_batch' => count($this->testResultIds),
                'successful' => $successfulProcessed,
                'successful_ids' => $successfulResults,
                'failed' => count($failedResults),
                'failed_ids' => $failedResults,
                'duration_seconds' => $duration,
                'end_time' => $endTime
            ]);

        } catch (Exception $e) {
            Log::channel('job')->error("ProcessAIReviewBatchJob batch {$this->batchNumber} failed", [
                'batch_number' => $this->batchNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'duration_seconds' => now()->diffInSeconds($startTime)
            ]);

            throw $e;
        }
    }

    /**
     * Process a single test result
     */
    private function processTestResult(int $testResultId, string $token): bool
    {
        return DB::transaction(function () use ($testResultId, $token) {
            $tr = TestResult::with([
                'patient',
                'testResultItems.panelPanelItem.panel.panelCategory',
                'testResultItems.referenceRange',
                'testResultItems.panelPanelItem.panelItem',
                'testResultItems.panelComments.masterPanelComment',
            ])->find($testResultId);

            if (!$tr || !$tr->patient) {
                Log::channel('job')->warning("Test result {$testResultId} not found or has no patient");
                return false;
            }

            // Get patient health details (with caching)
            $patientIcno = $tr->patient->icno ?? null;
            if (!$patientIcno) {
                Log::channel('job')->warning("Patient ICNO is null for test result {$testResultId}", [
                    'test_result_id' => $testResultId,
                    'patient_id' => $tr->patient->id ?? 'unknown'
                ]);
                return false;
            }
            $healthDetails = $this->getPatientHealthDetails($patientIcno);

            // Build patient info with null value logging
            $patientAge = $tr->patient->age ?? 'Unknown';
            if ($patientAge === 'Unknown') {
                Log::channel('job')->warning('Patient age is null, replaced with Unknown', [
                    'test_result_id' => $testResultId,
                    'patient_id' => $tr->patient->id,
                    'patient_icno' => $tr->patient->icno
                ]);
            }
            $patientInfo = array_merge(['Age' => $patientAge], $healthDetails ?? []);

            // Update patient gender if missing
            if (is_null($tr->patient->gender) && isset($healthDetails['Gender'])) {
                $tr->patient->gender = $healthDetails['Gender'];
                $tr->patient->save();
            }

            if (!$tr->patient->gender && isset($healthDetails['Gender'])) {
                $patientInfo['Gender'] = $healthDetails['Gender'];
            } else {
                $patientGender = $tr->patient->gender ?? 'Unknown';
                if ($patientGender === 'Unknown') {
                    Log::channel('job')->warning('Patient gender is null, replaced with Unknown', [
                        'test_result_id' => $testResultId,
                        'patient_id' => $tr->patient->id,
                        'patient_icno' => $tr->patient->icno
                    ]);
                }
                $patientInfo['Gender'] = $patientGender;
            }

            // Process test result items with new categorization logic
            if (!$tr->testResultItems || $tr->testResultItems->isEmpty()) {
                Log::channel('job')->warning("Test result {$testResultId} has no test result items");
                return false;
            }
            $categorizedItems = $this->categorizeTestResultItems($tr->testResultItems);

            if (empty($categorizedItems)) {
                Log::channel('job')->warning("No valid test result items for test result {$testResultId}");
                return false;
            }

            if (!$tr->reported_date) {
                Log::channel('job')->warning('Test result reported_date is null, using current date', [
                    'test_result_id' => $testResultId,
                    'test_result_lab_no' => $tr->lab_no
                ]);
            }
            $reportDate = $tr->reported_date ? Carbon::parse($tr->reported_date)->format('Y-m-d') : now()->format('Y-m-d');
            $finalResults[$reportDate] = $categorizedItems;

            $testResultData = [
                'Health History' => $patientInfo,
                'Blood Test Results' => $finalResults
            ];

            // Make API call with rate limiting
            $response = $this->makeRateLimitedApiCall($token, $testResultData);

            if (!$response) {
                Log::channel('job')->warning("API response is null for test result {$testResultId}");
                return false;
            }

            if (!$response->successful()) {
                Log::channel('job')->error("API response failed for test result {$testResultId}", [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'headers' => $response->headers()
                ]);
                return false;
            }

            $responseData = $response->json();

            if ($responseData['ai_analysis']['success'] && $responseData['ai_analysis']['status'] == 200) {
                $result = $this->doctorReviewController->convertTableBlock($responseData['ai_analysis']['answer']);

                // Store the generated review
                $this->doctorReviewController->store($tr->id, $testResultData, $result);

                // Mark as reviewed
                $tr->is_reviewed = true;
                $tr->save();
            } else {
                Log::channel('job')->error("AI analysis returned error status for test result {$testResultId}", [
                    'response' => $responseData,
                    'test_result_id' => $testResultId
                ]);
                return false;
            }

            return true;
        });
    }

    /**
     * Get patient health details with caching
     */
    private function getPatientHealthDetails(string $icno): array
    {
        $cacheKey = "patient_history_{$icno}";

        return Cache::remember($cacheKey, 3600, function () use ($icno) { // Cache for 1 hour
            $healthDetails = [];

            try {
                $checkRecords = $this->myHealthService->getCheckRecordIdByIC($icno);

                if ($checkRecords) {
                    foreach ($checkRecords as $cr) {
                        $recordId = $cr->id;
                        $recordGender = $cr->gender;
                        $recordDate = Carbon::parse($cr->date_time)->format('Y-m-d');

                        if (!isset($healthDetails['Gender'])) {
                            $healthDetails['Gender'] = $recordGender == 1 ? Patient::GENDER_MALE : Patient::GENDER_FEMALE;
                        }

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
                        }
                    }
                }
            } catch (Exception $e) {
                Log::channel('job')->error("Error fetching patient health details for {$icno}", [
                    'icno' => $icno,
                    'error' => $e->getMessage()
                ]);
            }

            return $healthDetails;
        });
    }

    /**
     * Categorize test result items using the new hierarchical structure
     */
    private function categorizeTestResultItems($testResultItems): array
    {
        $categorizedItems = [];

        foreach ($testResultItems as $ri) {
            try {
                // More detailed null checking
                if (!$ri) {
                    Log::channel('job')->debug('Skipping null test result item');
                    continue;
                }

                if (!$ri->panelPanelItem) {
                    Log::channel('job')->debug('Skipping test result item - no panelPanelItem', [
                        'result_item_id' => $ri->id ?? 'unknown'
                    ]);
                    continue;
                }

                if (!$ri->panelPanelItem->panelItem) {
                    Log::channel('job')->debug('Skipping test result item - no panelItem', [
                        'result_item_id' => $ri->id ?? 'unknown',
                        'panel_panel_item_id' => $ri->panelPanelItem->id ?? 'unknown'
                    ]);
                    continue;
                }

                if (!$ri->panelPanelItem->panel) {
                    Log::channel('job')->debug('Skipping test result item - no panel', [
                        'result_item_id' => $ri->id ?? 'unknown',
                        'panel_panel_item_id' => $ri->panelPanelItem->id ?? 'unknown'
                    ]);
                    continue;
                }

                $panelItemName = $ri->panelPanelItem->panelItem->name;
                if (!$panelItemName) {
                    Log::channel('job')->warning('Panel item name is null, replaced with Unknown Item', [
                        'test_result_item_id' => $ri->id,
                        'panel_item_id' => $ri->panelPanelItem->panelItem->id ?? 'unknown'
                    ]);
                    $panelItemName = 'Unknown Item';
                }

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

                // Get flag description
                $flagDescription = $ri->flag;
                if (!empty($ri->flag)) {
                    try {
                        $resultLibrary = ResultLibrary::where('code', '0078')
                            ->where('value', $ri->flag)
                            ->first();
                        if ($resultLibrary && !empty($resultLibrary->description)) {
                            $flagDescription = trim(preg_replace('/\s*\([^)]*\)/', '', $resultLibrary->description));
                        }
                    } catch (Exception $e) {
                        Log::channel('job')->error('Error fetching flag description', [
                            'flag' => $ri->flag,
                            'result_item_id' => $ri->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                $panelItemName = $ri->panelPanelItem->panelItem->name;
                if (!$panelItemName) {
                    Log::channel('job')->warning('Panel item name is null, replaced with Unknown Item', [
                        'test_result_item_id' => $ri->id,
                        'panel_item_id' => $ri->panelPanelItem->panelItem->id ?? 'unknown'
                    ]);
                    $panelItemName = 'Unknown Item';
                }

                $itemData = [
                    'panel_item_name' => $panelItemName,
                    'result_value' => $ri->value ?? null,
                    'panel_item_unit' => $ri->panelPanelItem->panelItem->unit ?? null,
                    'result_status' => $flagDescription ?? null,
                    'reference_range' => null,
                    'comments' => []
                ];

                // Add reference range
                if ($ri->reference_range_id && $ri->referenceRange) {
                    $itemData['reference_range'] = $ri->referenceRange->value;
                }

                // Add comments
                if ($ri->panelComments && !$ri->panelComments->isEmpty()) {
                    foreach ($ri->panelComments as $pc) {
                        if ($pc && $pc->masterPanelComment && !empty($pc->masterPanelComment->comment)) {
                            $itemData['comments'][] = $pc->masterPanelComment->comment;
                        }
                    }
                }

                // Add item to panel (simplified structure)
                $categorizedItems[$panelName][] = $itemData;

            } catch (Exception $e) {
                Log::channel('job')->error('Error processing test result item', [
                    'result_item_id' => $ri->id ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $categorizedItems;
    }

    /**
     * Make API call with rate limiting (5 requests per second)
     */
    private function makeRateLimitedApiCall(string $token, array $testResultData)
    {
        $executed = RateLimiter::attempt(
            'ai_api_calls',
            5, // 5 requests per second
            function () use ($token, $testResultData) {
                return Http::timeout(60)
                    ->withHeaders(['Authorization' => 'Bearer ' . $token])
                    ->post(config('credentials.ai_review.analysis'), $testResultData);
            },
            1 // Per 1 second
        );

        if (!$executed) {
            Log::channel('job')->warning('Rate limit exceeded, waiting before retry');
            sleep(1); // Wait 1 second if rate limit hit

            // Retry once
            return Http::timeout(60)
                ->withHeaders(['Authorization' => 'Bearer ' . $token])
                ->post(config('credentials.ai_review.analysis'), $testResultData);
        }

        return $executed;
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::channel('job')->error("ProcessAIReviewBatchJob batch {$this->batchNumber} failed permanently", [
            'batch_number' => $this->batchNumber,
            'test_result_ids' => $this->testResultIds,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
            'attempts' => $this->attempts()
        ]);
    }
}
