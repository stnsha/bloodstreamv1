<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\ResultLibrary;
use App\Models\TestResult;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class TestResultCompilerService
{
    protected $myHealthService;
    protected $logChannel;

    public function __construct(MyHealthService $myHealthService)
    {
        $this->myHealthService = $myHealthService;
        $this->logChannel = $this->determineLogChannel();
    }

    /**
     * Determine the appropriate log channel based on context
     */
    protected function determineLogChannel(): string
    {
        // If we're in a queue job context, use job channel
        if (app()->bound('queue.job')) {
            return 'job';
        }

        // If it's an ODB API request, use odb-log channel
        if (request()->is('api/odb/*')) {
            return 'odb-log';
        }

        // Default to standard logging
        return config('logging.default');
    }

    /**
     * Fetch single test result with all relationships
     */
    public function fetchTestResult(int $testResultId): TestResult
    {
        $tr = TestResult::with($this->getEagerLoadRelations())
            ->where('is_reviewed', false)
            ->where('is_completed', true)
            ->where('id', $testResultId)
            ->first();

        if (!$tr) {
            throw new RuntimeException("Test result not found: {$testResultId}");
        }

        return $tr;
    }

    /**
     * Fetch test result by IC or refid
     */
    public function fetchTestResultByIdentifier(string $icno, ?string $refid = null): TestResult
    {
        $tr = TestResult::with($this->getEagerLoadRelations())
            ->where('is_reviewed', false)
            ->where('is_completed', true)
            ->whereHas('patient', function ($query) use ($icno) {
                $query->where('icno', $icno);
            })
            ->latest()
            ->first();

        // Fallback to search by refid if provided
        if (!$tr && $refid) {
            $tr = TestResult::with($this->getEagerLoadRelations())
                ->where('ref_id', $refid)
                ->where('is_reviewed', false)
                ->where('is_completed', true)
                ->latest()
                ->first();
        }

        if (!$tr) {
            throw new RuntimeException("Test result not found for IC: {$icno}, refid: {$refid}");
        }

        return $tr;
    }

    /**
     * Fetch multiple test results for bulk processing
     */
    public function fetchBulkTestResults(array $items): array
    {
        $results = [];

        foreach ($items as $item) {
            try {
                $testResult = $this->fetchTestResultByIdentifier($item['icno'], $item['refid'] ?? null);
                $compiledData = $this->compileTestResultData($testResult);

                $results[] = [
                    'test_result' => $testResult,
                    'icno' => $item['icno'],
                    'refid' => $item['refid'] ?? null,
                    'compiled_data' => $compiledData
                ];
            } catch (Exception $e) {
                // Log and skip invalid items
                Log::channel($this->logChannel)->warning('Failed to fetch test result', [
                    'icno' => $item['icno'],
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $results;
    }

    /**
     * Compile complete test result data with MyHealth history
     */
    public function compileTestResultData(TestResult $testResult): array
    {
        $patientInfo = $this->gatherPatientHealthHistory($testResult);
        $categorizedItems = $this->categorizeTestResultItems($testResult);
        $reportDate = Carbon::parse($testResult->reported_date)->format('Y-m-d');

        return [
            'Health History' => $patientInfo,
            'Blood Test Results' => [
                $reportDate => $categorizedItems
            ]
        ];
    }

    /**
     * Gather patient health history from MyHealth
     * EXTRACTED FROM BOTH CONTROLLERS - 100% IDENTICAL
     */
    protected function gatherPatientHealthHistory(TestResult $testResult): array
    {
        $icno = $testResult->patient->icno;
        $checkRecords = $this->myHealthService->getCheckRecordIdByIC($icno);

        $patientInfo = [
            'Age' => $testResult->patient->age
        ];

        $healthDetails = [];

        if ($checkRecords && $checkRecords->isNotEmpty()) {
            // Batch load all record details to avoid N+1 query (PERFORMANCE OPTIMIZATION)
            $recordIds = $checkRecords->pluck('id')->toArray();
            $allRecordDetails = $this->myHealthService->getRecordDetailsBatch($recordIds);

            foreach ($checkRecords as $cr) {
                $recordId = $cr->id;
                $recordGender = $cr->gender;
                $recordDate = Carbon::parse($cr->date_time)->format('Y-m-d');

                if (is_null($testResult->patient->gender)) {
                    $testResult->patient->gender = $recordGender == 1
                        ? Patient::GENDER_MALE
                        : Patient::GENDER_FEMALE;
                    $testResult->patient->save();
                }

                $patientInfo['Gender'] = $testResult->patient->gender;

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

        return $patientInfo;
    }

    /**
     * Categorize test result items by panel
     * EXTRACTED FROM BOTH CONTROLLERS - 98% IDENTICAL
     */
    protected function categorizeTestResultItems(TestResult $testResult): array
    {
        $categorizedItems = [];
        $validItemsCount = 0;

        if ($testResult->testResultItems->isEmpty()) {
            throw new RuntimeException("No test result items found for test result: {$testResult->id}");
        }

        // Pre-load all ResultLibrary records to avoid N+1 query (PERFORMANCE OPTIMIZATION)
        $flags = $testResult->testResultItems->pluck('flag')->filter()->unique();
        $resultLibraries = [];
        if ($flags->isNotEmpty()) {
            $resultLibraries = ResultLibrary::where('code', '0078')
                ->whereIn('value', $flags->toArray())
                ->get()
                ->keyBy('value')
                ->toArray();
        }

        foreach ($testResult->testResultItems as $ri) {
            try {
                if (!$ri || !$ri->id) {
                    Log::channel($this->logChannel)->warning('Invalid result item', ['test_result_id' => $testResult->id]);
                    continue;
                }

                if (!$ri->panelPanelItem) {
                    Log::channel($this->logChannel)->warning('Test result item missing panel relationship', [
                        'result_item_id' => $ri->id,
                        'test_result_id' => $testResult->id
                    ]);
                    continue;
                }

                if (!$ri->panelPanelItem->panelItem) {
                    Log::channel($this->logChannel)->warning('Test result item missing panel item relationship', [
                        'result_item_id' => $ri->id,
                        'test_result_id' => $testResult->id
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

                $flagDescription = $this->resolveFlagDescription($ri->flag, $resultLibraries);

                $itemData = [
                    'panel_item_name' => $panelItemName,
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
                        Log::channel($this->logChannel)->warning('Error accessing reference range', [
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
                        Log::channel($this->logChannel)->warning('Error processing panel comments', [
                            'error' => $e->getMessage(),
                            'result_item_id' => $ri->id
                        ]);
                    }
                }

                // Add item to panel (simplified structure)
                $categorizedItems[$panelName][] = $itemData;
                $validItemsCount++;
            } catch (Exception $e) {
                Log::channel($this->logChannel)->error('Error processing test result item', [
                    'error' => $e->getMessage(),
                    'result_item_id' => $ri->id ?? 'unknown',
                    'test_result_id' => $testResult->id,
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        if ($validItemsCount === 0) {
            throw new RuntimeException("No valid test result items for test result: {$testResult->id}");
        }

        return $categorizedItems;
    }

    /**
     * Resolve flag description from ResultLibrary
     */
    protected function resolveFlagDescription(?string $flag, array $resultLibraries): ?string
    {
        if (empty($flag)) {
            return null;
        }

        try {
            // Use pre-loaded ResultLibrary (NO QUERY - PERFORMANCE OPTIMIZATION)
            $resultLibrary = $resultLibraries[$flag] ?? null;
            if ($resultLibrary && !empty($resultLibrary->description)) {
                // Remove content within parentheses and trim whitespace
                return trim(preg_replace('/\s*\([^)]*\)/', '', $resultLibrary->description));
            }
        } catch (Exception $e) {
            Log::channel($this->logChannel)->error('Error fetching flag description from ResultLibrary', [
                'error' => $e->getMessage(),
                'flag' => $flag
            ]);
        }

        return $flag;
    }

    /**
     * Get relationships to eager load
     */
    protected function getEagerLoadRelations(): array
    {
        return [
            'patient',
            'testResultItems.panelPanelItem.panel.panelCategory',
            'testResultItems.referenceRange',
            'testResultItems.panelPanelItem.panelItem',
            'testResultItems.panelComments.masterPanelComment',
        ];
    }
}